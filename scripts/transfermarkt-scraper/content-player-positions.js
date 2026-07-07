// content-player-positions.js — Extracts secondary positions from a Transfermarkt player profile page.
//
// Target URL pattern: https://www.transfermarkt.com/{player-slug}/profil/spieler/{id}
//
// Output format:
// {
//   "id": "581678",
//   "positions": ["Right Winger", "Centre-Forward"]
// }
//
// DOM structure (as of 2025):
//   div.detail-position
//     div.detail-position__box
//       div.detail-position__inner-box        ← main position lives here
//         dl
//           dt.detail-position__title  "Main position:"
//           dd.detail-position__position  "Centre-Forward"
//       div.detail-position__position         ← other positions container
//         dl
//           dt.detail-position__title  "Other position:"
//           dd.detail-position__position  "Left Winger"
//           dd.detail-position__position  "Right Winger"

(function () {
  const url = window.location.href;

  // Extract player ID from URL
  const playerIdMatch = url.match(/\/spieler\/(\d+)/);
  const playerId = playerIdMatch ? playerIdMatch[1] : '';

  const positions = [];

  // Grab only the "Other position" dd elements — those NOT inside .detail-position__inner-box
  // The inner-box holds the main position; sibling div holds other/secondary positions.
  const otherDds = document.querySelectorAll(
    '.detail-position__box > .detail-position__position dd.detail-position__position'
  );

  otherDds.forEach(dd => {
    const text = (dd.textContent || '').replace(/\s+/g, ' ').trim();
    if (text && !positions.includes(text)) {
      positions.push(text);
    }
  });

  return {
    id: playerId,
    positions
  };
})();
