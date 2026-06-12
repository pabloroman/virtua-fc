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
