/**
 * Tests for the Transfermarkt scraper's github.js — specifically `verify()`,
 * the pre-flight the season refresh runs before it starts scraping.
 *
 * The failure it exists to prevent is silent and expensive: an expired PAT used
 * to surface only on the push at the *end* of a full-season scrape, as a raw
 * "GET /git/ref/heads/season-data/2026 → 401: Bad credentials". So what matters
 * here is that each way the credentials can be wrong maps to a message naming
 * the actual cause — a fine-grained PAT reports a repo it was never granted as
 * a 404, which reads nothing like a permissions problem unless we say so.
 */
import { describe, it, expect, beforeAll, beforeEach, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

let GitHubClient;

beforeAll(() => {
    // github.js is a plain script that attaches to the global object (it is
    // loaded via importScripts in the MV3 service worker, not imported).
    globalThis.self = globalThis;
    const src = readFileSync(
        resolve(__dirname, '../../scripts/transfermarkt-scraper/github.js'),
        'utf8',
    );
    // eslint-disable-next-line no-new-func
    new Function(src)();
    GitHubClient = globalThis.GitHubClient;
});

const response = (status, body = {}, headers = {}) => ({
    status,
    ok: status >= 200 && status < 300,
    statusText: `status ${status}`,
    json: async () => body,
    headers: { get: name => headers[name] ?? null },
});

const REPO = { full_name: 'pabloroman/virtua-fc', permissions: { push: true } };
const REF = { object: { sha: 'abc123' } };

let fetchMock;

beforeEach(() => {
    fetchMock = vi.fn();
    globalThis.fetch = fetchMock;
});

const client = () => new GitHubClient('github_pat_x', 'pabloroman/virtua-fc');

describe('verify', () => {
    it('reports an expired or revoked token as a credentials problem', async () => {
        fetchMock.mockResolvedValueOnce(response(401, { message: 'Bad credentials' }));

        await expect(client().verify('main')).rejects.toThrow(/Bad credentials — the token is invalid, expired or revoked/);
    });

    it('reads a 404 on the repo as missing access, not a missing repo', async () => {
        fetchMock.mockResolvedValueOnce(response(404, { message: 'Not Found' }));

        // A fine-grained PAT that does not list the repository gets a 404 here,
        // so the message has to point at the token's repository selection.
        await expect(client().verify('main')).rejects.toThrow(/does not list this repository/);
    });

    it('fails when the base branch cannot be read', async () => {
        fetchMock
            .mockResolvedValueOnce(response(200, REPO))
            .mockResolvedValueOnce(response(404, { message: 'Not Found' }));

        await expect(client().verify('main')).rejects.toThrow(/Base branch "main" is not readable/);
    });

    it('returns the repo and token expiry once both calls succeed', async () => {
        fetchMock
            .mockResolvedValueOnce(response(200, REPO, {
                'github-authentication-token-expiration': '2026-10-01 12:00:00 UTC',
            }))
            .mockResolvedValueOnce(response(200, REF));

        await expect(client().verify('main')).resolves.toEqual({
            repo: 'pabloroman/virtua-fc',
            expiresAt: '2026-10-01 12:00:00 UTC',
            canWrite: true,
        });
    });

    it('checks the base branch ref, which is the first call a push makes', async () => {
        fetchMock
            .mockResolvedValueOnce(response(200, REPO))
            .mockResolvedValueOnce(response(200, REF));

        await client().verify('main');

        expect(fetchMock.mock.calls[0][0]).toBe('https://api.github.com/repos/pabloroman/virtua-fc');
        expect(fetchMock.mock.calls[1][0]).toBe('https://api.github.com/repos/pabloroman/virtua-fc/git/ref/heads/main');
    });

    it('flags a read-only token instead of failing — write access cannot be proven without writing', async () => {
        fetchMock
            .mockResolvedValueOnce(response(200, { ...REPO, permissions: { push: false } }))
            .mockResolvedValueOnce(response(200, REF));

        await expect(client().verify('main')).resolves.toMatchObject({ canWrite: false });
    });

    it('assumes write access when the repo endpoint reports no permissions block', async () => {
        fetchMock
            .mockResolvedValueOnce(response(200, { full_name: 'pabloroman/virtua-fc' }))
            .mockResolvedValueOnce(response(200, REF));

        await expect(client().verify('main')).resolves.toMatchObject({ canWrite: true, expiresAt: null });
    });
});

/**
 * A stand-in for the Git Data endpoints `commitFiles` drives, so the retry can
 * be tested as a sequence rather than as a fixed list of canned responses.
 * `failPatchTimes` makes the ref update fail as a non-fast-forward that many
 * times, moving the head each time — what CI's normalize commit does when it
 * lands mid-push.
 */
const gitDataServer = ({ failPatchTimes = 0, patchError = null } = {}) => {
    const state = { head: 'head1', trees: [], commits: [] };
    let remainingFailures = failPatchTimes;
    let movedHeads = 0;

    globalThis.fetch = vi.fn(async (url, init = {}) => {
        const method = init.method || 'GET';
        const path = url.replace('https://api.github.com/repos/pabloroman/virtua-fc', '');
        const body = init.body ? JSON.parse(init.body) : null;

        if (method === 'GET' && path.startsWith('/git/ref/heads/')) {
            return response(200, { object: { sha: state.head } });
        }
        if (method === 'GET' && path.startsWith('/git/commits/')) {
            return response(200, { tree: { sha: `tree-of-${path.split('/').pop()}` } });
        }
        if (method === 'POST' && path === '/git/trees') {
            state.trees.push(body);
            return response(201, { sha: `tree${state.trees.length}` });
        }
        if (method === 'POST' && path === '/git/commits') {
            state.commits.push(body);
            return response(201, { sha: `commit${state.commits.length}` });
        }
        if (method === 'PATCH' && path.startsWith('/git/refs/heads/')) {
            if (patchError) return response(patchError, { message: 'nope' });
            if (remainingFailures > 0) {
                remainingFailures -= 1;
                movedHeads += 1;
                state.head = `head${movedHeads + 1}`;
                return response(422, { message: 'Update is not a fast forward' });
            }
            state.head = body.sha;
            return response(200, { object: { sha: body.sha } });
        }
        throw new Error(`unexpected ${method} ${path}`);
    });

    return state;
};

const FILES = [{ path: 'data/2026/UCL/teams.json', content: '{}' }];

describe('commitFiles — a branch head that moves mid-push', () => {
    it('rebuilds the commit on the new head and pushes it', async () => {
        const state = gitDataServer({ failPatchTimes: 1 });

        await expect(client().commitFiles('season-data/2026', 'main', FILES, 'msg')).resolves.toBe('commit2');

        // The second attempt parents off the head CI moved to, and takes its
        // base tree from there — so CI's commit survives instead of being
        // reverted by the retry.
        expect(state.commits[0].parents).toEqual(['head1']);
        expect(state.commits[1].parents).toEqual(['head2']);
        expect(state.trees[1].base_tree).toBe('tree-of-head2');
    });

    it('carries file contents inline in the tree, in one request per attempt', async () => {
        // One write request per file put a ~70-file European pool push against
        // GitHub's secondary rate limit; the trees API writes the blobs itself.
        const state = gitDataServer();
        const files = [
            { path: 'data/2026/EUR/11.json', content: '{"a":1}' },
            { path: 'data/2026/EUR/418.json', content: '{"b":2}' },
        ];

        await client().commitFiles('season-data/2026', 'main', files, 'msg');

        expect(state.trees).toHaveLength(1);
        expect(state.trees[0].tree).toEqual([
            { path: 'data/2026/EUR/11.json', mode: '100644', type: 'blob', content: '{"a":1}' },
            { path: 'data/2026/EUR/418.json', mode: '100644', type: 'blob', content: '{"b":2}' },
        ]);
        // Four requests regardless of file count: read ref, read commit, tree, commit, ref update.
        expect(globalThis.fetch.mock.calls.filter(([url]) => url.includes('/git/blobs'))).toHaveLength(0);
    });

    it('reports each phase so a long push is visible and the worker stays awake', async () => {
        gitDataServer({ failPatchTimes: 1 });
        const phases = [];

        await client().commitFiles('season-data/2026', 'main', FILES, 'msg', p => phases.push(p));

        expect(phases).toEqual([
            'Uploading 1 file…',
            'Creating the commit…',
            'Updating the branch…',
            'Branch moved — rebuilding (attempt 2)…',
            'Uploading 1 file…',
            'Creating the commit…',
            'Updating the branch…',
        ]);
    });

    it('gives up after three collisions with a message naming the branch', async () => {
        gitDataServer({ failPatchTimes: 3 });

        await expect(client().commitFiles('season-data/2026', 'main', FILES, 'msg'))
            .rejects.toThrow(/"season-data\/2026" moved under the push 3 times in a row/);
    });

    it('does not retry a failure that is not a fast-forward conflict', async () => {
        const state = gitDataServer({ patchError: 403 });

        await expect(client().commitFiles('season-data/2026', 'main', FILES, 'msg')).rejects.toThrow(/403/);
        expect(state.commits).toHaveLength(1);
    });
});
