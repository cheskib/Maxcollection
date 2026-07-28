# Wishlist

Ideas approved for the wishlist by the owner — not scheduled, not built.
Each entry notes where it came from so context isn't lost.

## Done

- **Duplicate-card awareness** — built 2026-07-29: ×N banners on item
  pages, the /duplicates trade list, and a summary link.
- **Live market value data** — built 2026-07-29 via PriceCharting /
  SportsCardsPro; awaiting the owner's PRICECHARTING_TOKEN in Railway
  to activate.
- **Card-type value cleanup** — built 2026-07-28 (Team Leaders /
  League Leaders / All-Star normalization).
- **Settings page** — built 2026-07-29: review threshold editable,
  models shown.

## Re-enable the Sets catalog

The Sets catalog (self-building set profiles with AI design histories)
is hidden — owner decision, "get rid of sets for now… or maybe just
hide it." Profiles still accrue silently; descriptions are not written
while hidden. To re-enable: restore the Home tile, the summary's By
Set section, and the DescribeSetJob dispatch in
ProcessingService::registerCardSet.
*(2026-07-28.)*

## Other items deferred in discussion

- **Set completion tracking** — "1987 Topps has 792 cards, you own 14"
  on each set page. *(Raised when the Sets catalog was designed.)*
- **User-added categories** — generic field set, managed from Settings.
  Owner decision: "leave it as is for now". *(Category review.)*
- **Search index at scale** — free-text search currently scans; fine at
  thousands of cards, will want a proper index around six figures.
  *(300,000-card target discussion.)*
- **Storage growth** — 300k cards × ~2 scans is real disk; the Railway
  volume will need enlarging as the collection grows. *(Same.)*
