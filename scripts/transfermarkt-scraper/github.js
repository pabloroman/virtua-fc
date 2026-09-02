// github.js — Minimal GitHub Git Data API client used by the background service
// worker to land a whole season's squad files as one commit on a branch and
// open/update a pull request.
//
// Loaded via importScripts in background.js; attaches to the global object.

(function (global) {
  const API = 'https://api.github.com';

  class GitHubClient {
    /**
     * @param {string} token  Fine-grained PAT scoped to the repo, with
     *                         Contents + Pull requests read/write.
     * @param {string} repo   "owner/name".
     */
    constructor(token, repo) {
      this.token = token;
      const [owner, name] = repo.split('/');
      this.owner = owner;
      this.name = name;
    }

    async request(method, path, body) {
      const res = await fetch(`${API}/repos/${this.owner}/${this.name}${path}`, {
        method,
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/vnd.github+json',
          'X-GitHub-Api-Version': '2022-11-28',
          'Content-Type': 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
      });

      if (res.status === 404) return { notFound: true };
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        const detail = data && data.message ? data.message : res.statusText;
        throw new Error(`GitHub ${method} ${path} → ${res.status}: ${detail}`);
      }
      return data;
    }

    /**
     * Check the credentials before a scrape commits to them. Exercises the
     * same three things a push needs — the token itself, access to the repo,
     * and a Contents read of the base branch (`commitFiles`' first call) —
     * and throws naming whichever failed.
     *
     * Write access cannot be proven without writing; `canWrite` reports what
     * the repo endpoint says about it, so the caller can warn rather than
     * refuse.
     */
    async verify(base) {
      // Not routed through request(): that maps 404 to { notFound } and drops
      // the response headers, and both matter here. A fine-grained PAT gets a
      // 404 (not a 403) for a repo it was never granted, and GitHub reports a
      // PAT's expiry in a header.
      const res = await fetch(`${API}/repos/${this.owner}/${this.name}`, {
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/vnd.github+json',
          'X-GitHub-Api-Version': '2022-11-28',
        },
      });
      const data = await res.json().catch(() => ({}));

      if (res.status === 401) {
        throw new Error(
          'Bad credentials — the token is invalid, expired or revoked. ' +
            'Fine-grained PATs expire (30 days by default); generate a new one and save it again.',
        );
      }
      if (res.status === 404) {
        throw new Error(
          `No access to ${this.owner}/${this.name}. The token is valid, so either the ` +
            'owner/repo is wrong or the PAT does not list this repository — a fine-grained ' +
            'token reports a repo it cannot see as "not found".',
        );
      }
      if (!res.ok) {
        throw new Error(`GitHub GET /repos/${this.owner}/${this.name} → ${res.status}: ${data.message || res.statusText}`);
      }

      const ref = await this.request('GET', `/git/ref/heads/${base}`);
      if (ref.notFound) {
        throw new Error(
          `Base branch "${base}" is not readable — it does not exist, or the token is ` +
            'missing the Contents permission.',
        );
      }

      return {
        repo: data.full_name || `${this.owner}/${this.name}`,
        // Absent for token types that do not report it (a non-expiring PAT).
        expiresAt: res.headers.get('github-authentication-token-expiration') || null,
        canWrite: !data.permissions || data.permissions.push === true,
      };
    }

    /**
     * Read and JSON-parse a file from `branch`, or null when it does not exist
     * there. Throws on any other failure so a caller can refuse to push rather
     * than silently overwriting data it failed to read.
     */
    async getFileJson(branch, path) {
      const file = await this.request(
        'GET',
        `/contents/${path.split('/').map(encodeURIComponent).join('/')}?ref=${encodeURIComponent(branch)}`,
      );
      if (file.notFound || !file.content) return null;

      // The contents API returns base64 with embedded newlines.
      const bytes = Uint8Array.from(atob(file.content.replace(/\n/g, '')), c => c.charCodeAt(0));
      return JSON.parse(new TextDecoder().decode(bytes));
    }

    async getRefSha(branch) {
      // Branch names (e.g. "season-data/2026") contain a slash that is part of
      // the ref path — it must not be percent-encoded.
      const ref = await this.request('GET', `/git/ref/heads/${branch}`);
      return ref.notFound ? null : ref.object.sha;
    }

    /**
     * Ensure `branch` exists (branching off `base` when absent) and return the
     * sha its head currently points at.
     */
    async ensureBranch(branch, base) {
      const existing = await this.getRefSha(branch);
      if (existing) return existing;

      const baseSha = await this.getRefSha(base);
      if (!baseSha) throw new Error(`Base branch "${base}" not found.`);

      await this.request('POST', '/git/refs', {
        ref: `refs/heads/${branch}`,
        sha: baseSha,
      });
      return baseSha;
    }

    /**
     * Commit a set of files to `branch` in a single commit (created off the
     * branch's current head) and return the new commit sha.
     *
     * @param {Array<{path: string, content: string}>} files
     */
    async commitFiles(branch, base, files, message) {
      const headSha = await this.ensureBranch(branch, base);
      const headCommit = await this.request('GET', `/git/commits/${headSha}`);
      const baseTree = headCommit.tree.sha;

      const tree = [];
      for (const file of files) {
        const blob = await this.request('POST', '/git/blobs', {
          content: file.content,
          encoding: 'utf-8',
        });
        tree.push({ path: file.path, mode: '100644', type: 'blob', sha: blob.sha });
      }

      const newTree = await this.request('POST', '/git/trees', {
        base_tree: baseTree,
        tree,
      });

      const commit = await this.request('POST', '/git/commits', {
        message,
        tree: newTree.sha,
        parents: [headSha],
      });

      await this.request('PATCH', `/git/refs/heads/${branch}`, {
        sha: commit.sha,
      });

      return commit.sha;
    }

    /**
     * Open a PR from `branch` into `base`, or return the existing open one.
     * Returns its html_url.
     */
    async ensurePullRequest(branch, base, title, body) {
      const open = await this.request(
        'GET',
        `/pulls?head=${this.owner}:${branch}&state=open`,
      );
      if (Array.isArray(open) && open.length > 0) {
        return open[0].html_url;
      }

      const pr = await this.request('POST', '/pulls', {
        title,
        head: branch,
        base,
        body: body || '',
      });
      return pr.html_url;
    }
  }

  global.GitHubClient = GitHubClient;
})(self);
