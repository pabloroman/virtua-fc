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
