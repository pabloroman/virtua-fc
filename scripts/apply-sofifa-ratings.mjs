#!/usr/bin/env node
// Augment teams.json players with SoFIFA ratings, in place, by linking sofifa
// player records to squad players *by team + name*.
//
// Whenever a data directory holds a `sofifa.json` next to a `teams.json`, this
// script copies four fields onto each matched squad player:
//   overall_score, potential, value, yearly_wage
// `GamePlayerTemplateService::prepareTemplateRow` already honors `overall_score`
// and `potential` when present on a player record; `value`/`yearly_wage` are
// carried through for reference/future use. Unmatched squad players keep their
// existing fields and fall back to the importer's market-value ability heuristic.
//
// Discovery: recursively scan data/ for files named `sofifa.json`; for each, the
// target is the sibling `teams.json` in the same directory. Pass a positional
// <dir> to restrict to one directory.
//
// sofifa.json shape:
//   { league, teams: [ { team, players: [ { name, full_name, overall_score,
//     potential, value, yearly_wage, currency }, ... ] }, ... ] }
// teams.json shape:
//   { id, code, name, seasonID, clubs: [ { name, players: [ { name, ... }, ... ] }, ... ] }
//
// Team resolution: each sofifa/teams.json name is reduced to a set of "core"
// identifying tokens (drop club-type acronyms like RC/CD/FC/CF/Real, generic
// descriptors, founding-year numbers and the leading "1." ordinal). A club is
// linked to the sofifa team it shares the most tokens with, so "Inter Milan"
// links to "Inter", "SSC Napoli" to "Napoli", and "Real Sporting de Gijón" to
// "Sporting Gijón". See teamCoreTokens / resolveSofifaTeam.
//
// Player matching, scoped to the resolved sofifa team, claiming as we go so one
// sofifa player never serves two squad players:
//   1. exact     — squad name equals a sofifa player's `name` or `full_name`.
//   2. token     — squad name tokens are a subset (either direction) of a sofifa
//                  player's `name`/`full_name` tokens, unique among unclaimed.
//   3. surname   — the squad player's last token uniquely identifies one unclaimed
//                  sofifa player in the team (by last token of name/full_name) and
//                  the first-name initials are compatible. Catches diminutives
//                  (Dani↔Daniel, Nico↔Nicolás) without cross-team mislinks.
//
// Behaviour: report-and-write. Partial coverage is expected (a squad player may
// simply not exist in sofifa), so unmatched players are a non-fatal warning. A
// club that fails to resolve to a sofifa team, or an invalid rating value, is an
// error for that club but does not abort the rest. Pass --dry-run to report
// without writing.
//
// Serialization matches teams.json: object keys sorted alphabetically, 2-space
// indent, trailing newline. The 5 top-level wrapper keys keep their insertion
// order so the only diff is the four fields added per player.

import { readFileSync, writeFileSync, readdirSync, existsSync, statSync } from "node:fs";
import { dirname, join, resolve, basename } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const DATA_DIR = join(ROOT, "data");

const argv = process.argv.slice(2);
const DRY_RUN = argv.includes("--dry-run");
const positional = argv.filter((a) => !a.startsWith("--"));
const SCAN_DIR = positional[0] ? resolve(process.cwd(), positional[0]) : DATA_DIR;

// The four fields we copy from a sofifa player onto a squad player.
const RATING_FIELDS = ["overall_score", "potential", "value", "yearly_wage"];

// Serialize like teams.json: alphabetical keys, 2-space indent, no trailing
// newline (the caller appends one). Identical to scripts/apply-wc-ratings.mjs.
function stringifySorted(value, indent = 2, depth = 0) {
  const pad = " ".repeat(indent * depth);
  const innerPad = " ".repeat(indent * (depth + 1));
  if (value === null || typeof value !== "object") {
    return JSON.stringify(value);
  }
  if (Array.isArray(value)) {
    if (value.length === 0) return "[]";
    const items = value.map((v) => innerPad + stringifySorted(v, indent, depth + 1));
    return "[\n" + items.join(",\n") + "\n" + pad + "]";
  }
  const keys = Object.keys(value).sort();
  if (keys.length === 0) return "{}";
  const items = keys.map(
    (k) => innerPad + JSON.stringify(k) + ": " + stringifySorted(value[k], indent, depth + 1),
  );
  return "{\n" + items.join(",\n") + "\n" + pad + "}";
}

// Serialize the top-level wrapper preserving ITS key insertion order (id, code,
// name, seasonID, clubs), while every nested object is sorted by stringifySorted.
// Keeps the metadata header stable so the diff is only the per-player fields.
function stringifyTeamsFile(data) {
  const keys = Object.keys(data);
  const items = keys.map(
    (k) => "  " + JSON.stringify(k) + ": " + stringifySorted(data[k], 2, 1),
  );
  return "{\n" + items.join(",\n") + "\n}\n";
}

// lowercase, strip diacritics, collapse internal whitespace, trim.
function normalizeName(raw) {
  return String(raw ?? "")
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "")
    .toLowerCase()
    .replace(/\s+/g, " ")
    .trim();
}

// Normalized token set, split on whitespace, hyphens and dots.
function tokenize(raw) {
  return new Set(
    normalizeName(raw)
      .split(/[\s.\-]+/)
      .filter(Boolean),
  );
}

// true if every token of `a` is in `b` (a ⊆ b).
function isSubset(a, b) {
  for (const t of a) if (!b.has(t)) return false;
  return true;
}

// true if either token set fully contains the other (and neither is empty).
function tokensCompatible(a, b) {
  if (a.size === 0 || b.size === 0) return false;
  return isSubset(a, b) || isSubset(b, a);
}

// Club-name prefixes/suffixes that carry no identifying weight, dropped before
// comparing team names so sofifa's "Real Sporting de Gijón" lines up with
// teams.json's "Sporting Gijón".
const TEAM_STOPWORDS = new Set([
  "rc", "cd", "ud", "sd", "ad", "fc", "cf", "real", "club", "balompie", "de", "futbol",
]);

// A few clubs sofifa and teams.json spell differently; fold the variant token
// onto a shared form so the names line up: FC Bayern München ↔ Bayern Munich,
// Olympique Lyonnais ↔ Olympique Lyon, Lille OSC ↔ LOSC Lille.
const TEAM_TOKEN_ALIASES = new Map([
  ["munchen", "munich"],
  ["lyonnais", "lyon"],
  ["losc", "lille"],
]);

// Core identifying tokens of a team name: normalized, split on whitespace AND
// dots (so "1. FC" and "FC St. Pauli" tokenize the same regardless of spacing),
// with stopwords, pure-number tokens (founding years like 1909 / "05", the
// leading "1." ordinal) dropped and aliases folded in.
function teamCoreTokens(raw) {
  const out = new Set();
  for (const t of normalizeName(raw).split(/[\s.]+/)) {
    if (!t || /^\d+$/.test(t) || TEAM_STOPWORDS.has(t)) continue;
    out.add(TEAM_TOKEN_ALIASES.get(t) ?? t);
  }
  return out;
}

// Resolve a teams.json club to its sofifa team by core-token overlap: the
// candidate sharing the most tokens wins, ties on overlap break toward the
// fewest non-shared tokens (so "Inter Milan" prefers "Inter" over "AC Milan"
// and "Paris FC" prefers "Paris FC" over "Paris Saint-Germain"). A genuine tie
// is left unresolved rather than guessed.
function resolveSofifaTeam(clubName, sofifaTeams) {
  const clubTokens = teamCoreTokens(clubName);
  if (clubTokens.size === 0) return null;
  let best = null;
  let bestOverlap = 0;
  let bestExtra = Infinity;
  let tied = false;
  for (const st of sofifaTeams) {
    if (st.tokens.size === 0) continue;
    let overlap = 0;
    for (const t of st.tokens) if (clubTokens.has(t)) overlap++;
    if (overlap === 0) continue;
    const extra = st.tokens.size + clubTokens.size - 2 * overlap;
    if (overlap > bestOverlap || (overlap === bestOverlap && extra < bestExtra)) {
      best = st.team;
      bestOverlap = overlap;
      bestExtra = extra;
      tied = false;
    } else if (overlap === bestOverlap && extra === bestExtra) {
      tied = true;
    }
  }
  return tied ? null : best;
}

// Mirror of GamePlayerTemplateService::resolveExplicitAbility — invalid is an
// error here (we refuse to write junk the importer would silently drop).
function validateAbility(raw) {
  if (raw === null || raw === undefined || raw === "") {
    return { ok: false, reason: "missing" };
  }
  const n = typeof raw === "number" ? raw : Number(raw);
  if (!Number.isInteger(n) || n < 1 || n > 99) {
    return { ok: false, reason: `not an integer in 1..99 (${JSON.stringify(raw)})` };
  }
  return { ok: true, value: n };
}

// value / yearly_wage: non-negative finite numbers (euros). Empty/null is ok and
// copied through as-is; a non-numeric value is an error.
function validateAmount(raw, field) {
  if (raw === null || raw === undefined || raw === "") {
    return { ok: true, value: raw ?? null };
  }
  const n = typeof raw === "number" ? raw : Number(raw);
  if (!Number.isFinite(n) || n < 0) {
    return { ok: false, reason: `${field} not a non-negative number (${JSON.stringify(raw)})` };
  }
  return { ok: true, value: n };
}

// Recursively collect every data/**/sofifa.json under `dir`.
function findSofifaFiles(dir) {
  const out = [];
  let entries;
  try {
    entries = readdirSync(dir, { withFileTypes: true });
  } catch {
    return out;
  }
  for (const entry of entries) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...findSofifaFiles(full));
    } else if (entry.isFile() && entry.name === "sofifa.json") {
      out.push(full);
    }
  }
  return out;
}

// Build a sofifa team's player pool: one record per player, with name candidates
// pre-normalized and a `used` flag for claiming.
function buildPool(sofifaTeam) {
  return sofifaTeam.players.map((p, i) => {
    const names = [p.name, p.full_name].filter((s) => s != null && String(s).trim() !== "");
    const tokenSets = names.map(tokenize);
    const surnames = new Set(tokenSets.map((t) => [...t].at(-1)).filter(Boolean));
    const initials = new Set(tokenSets.map((t) => [...t][0]?.[0]).filter(Boolean));
    return {
      i,
      player: p,
      used: false,
      norms: names.map(normalizeName),
      tokenSets,
      surnames,
      initials,
    };
  });
}

// Copy the four rating fields from a sofifa player onto a squad player. Returns
// an error string if any rating value is invalid, else null.
function applyRatings(squadPlayer, sofifaPlayer, label) {
  const overall = validateAbility(sofifaPlayer.overall_score);
  if (!overall.ok) return `"${label}": overall_score ${overall.reason}`;
  const potential = validateAbility(sofifaPlayer.potential);
  if (!potential.ok) return `"${label}": potential ${potential.reason}`;
  if (potential.value < overall.value) {
    return `"${label}": potential ${potential.value} < overall_score ${overall.value}`;
  }
  const value = validateAmount(sofifaPlayer.value, "value");
  if (!value.ok) return `"${label}": ${value.reason}`;
  const wage = validateAmount(sofifaPlayer.yearly_wage, "yearly_wage");
  if (!wage.ok) return `"${label}": ${wage.reason}`;

  squadPlayer.overall_score = overall.value;
  squadPlayer.potential = potential.value;
  squadPlayer.value = value.value;
  squadPlayer.yearly_wage = wage.value;
  return null;
}

// Link one club's squad to its sofifa team. Mutates squad players in place.
// Returns { matched, unmatched: [names], errors: [msgs] }.
function matchClub(club, sofifaTeam) {
  const pool = buildPool(sofifaTeam);
  const unmatched = [];
  const errors = [];
  let matched = 0;

  const claim = (entry, squadPlayer, label) => {
    const err = applyRatings(squadPlayer, entry.player, label);
    if (err) {
      errors.push(err);
      return;
    }
    entry.used = true;
    matched++;
  };

  for (const sp of club.players) {
    const label = String(sp.name ?? "");
    const norm = normalizeName(sp.name);
    const tokens = tokenize(sp.name);

    // Pass 1 — exact normalized name on either sofifa name candidate.
    let cand = pool.filter((e) => !e.used && e.norms.includes(norm));
    let uniq = [...new Set(cand.map((e) => e.i))];

    // Pass 2 — partial token subset (either direction).
    if (uniq.length !== 1) {
      cand = pool.filter((e) => !e.used && e.tokenSets.some((t) => tokensCompatible(tokens, t)));
      uniq = [...new Set(cand.map((e) => e.i))];
    }

    // Pass 3 — unique surname in the team with a compatible first initial.
    if (uniq.length !== 1 && tokens.size > 0) {
      const surname = [...tokens].at(-1);
      const initial = [...tokens][0]?.[0];
      cand = pool.filter(
        (e) =>
          !e.used &&
          e.surnames.has(surname) &&
          (initial ? e.initials.has(initial) : true),
      );
      uniq = [...new Set(cand.map((e) => e.i))];
    }

    if (uniq.length === 1) {
      claim(pool[uniq[0]], sp, label);
    } else {
      unmatched.push(label);
    }
  }

  return { matched, unmatched, errors };
}

function processPair(sofifaPath) {
  const dir = dirname(sofifaPath);
  const teamsPath = join(dir, "teams.json");
  const rel = (p) => p.slice(ROOT.length + 1);

  if (!existsSync(teamsPath)) {
    console.warn(`! ${rel(sofifaPath)}: no sibling teams.json — skipped`);
    return { skipped: true };
  }

  const sofifa = JSON.parse(readFileSync(sofifaPath, "utf8"));
  const teams = JSON.parse(readFileSync(teamsPath, "utf8"));

  // Pre-compute each sofifa team's core token set once; clubs are resolved by
  // token overlap (see resolveSofifaTeam).
  const sofifaTeams = (sofifa.teams ?? []).map((t) => ({
    team: t,
    tokens: teamCoreTokens(t.team),
  }));

  const reports = [];
  const errors = [];
  const unresolvedClubs = [];
  let totalMatched = 0;
  let totalPlayers = 0;

  for (const club of teams.clubs ?? []) {
    const sofifaTeam = resolveSofifaTeam(club.name, sofifaTeams);
    if (!sofifaTeam) {
      unresolvedClubs.push(`${club.name} [tokens="${[...teamCoreTokens(club.name)].join(" ")}"]`);
      continue;
    }
    const { matched, unmatched, errors: clubErrors } = matchClub(club, sofifaTeam);
    totalMatched += matched;
    totalPlayers += club.players.length;
    reports.push({ club: club.name, matched, total: club.players.length, unmatched });
    for (const e of clubErrors) errors.push(`[${club.name}] ${e}`);
  }

  // Report
  console.log(`\n${rel(teamsPath)}`);
  for (const r of reports) {
    console.log(`  ${r.club}: ${r.matched}/${r.total} matched`);
  }
  console.log(`  TOTAL: ${totalMatched}/${totalPlayers} matched`);

  const allUnmatched = reports.flatMap((r) => r.unmatched.map((n) => `${r.club}: ${n}`));
  if (allUnmatched.length) {
    console.log(`  ${allUnmatched.length} unmatched (no sofifa rating, left as-is):`);
    for (const u of allUnmatched) console.log(`    - ${u}`);
  }
  if (unresolvedClubs.length) {
    console.warn(`  ${unresolvedClubs.length} club(s) did not resolve to a sofifa team:`);
    for (const c of unresolvedClubs) console.warn(`    ! ${c}`);
  }
  if (errors.length) {
    console.error(`  ${errors.length} rating value error(s):`);
    for (const e of errors) console.error(`    ! ${e}`);
  }

  if (DRY_RUN) {
    return { teamsPath, totalMatched, totalPlayers, written: false };
  }

  writeFileSync(teamsPath, stringifyTeamsFile(teams));
  console.log(`  Wrote ${rel(teamsPath)}`);
  return { teamsPath, totalMatched, totalPlayers, written: true };
}

function main() {
  if (!existsSync(SCAN_DIR)) {
    console.error(`Scan directory not found: ${SCAN_DIR}`);
    process.exit(1);
  }

  // Allow pointing directly at a directory that contains sofifa.json, or a tree.
  let sofifaFiles;
  const direct = join(SCAN_DIR, "sofifa.json");
  if (statSync(SCAN_DIR).isDirectory() && existsSync(direct)) {
    sofifaFiles = [direct];
  } else {
    sofifaFiles = findSofifaFiles(SCAN_DIR).sort();
  }

  if (sofifaFiles.length === 0) {
    console.error(`No sofifa.json found under ${SCAN_DIR}`);
    process.exit(1);
  }

  console.log(
    `Found ${sofifaFiles.length} sofifa.json file(s)${DRY_RUN ? " (dry run)" : ""}.`,
  );

  let wrote = 0;
  for (const f of sofifaFiles) {
    const res = processPair(f);
    if (res.written) wrote++;
  }

  console.log(
    DRY_RUN
      ? `\nDry run complete. ${sofifaFiles.length} file(s) inspected, nothing written.`
      : `\nDone: updated ${wrote} teams.json file(s).`,
  );
}

main();
