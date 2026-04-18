## Scanwell Backend

The barcode scanning engine was redesigned to make vendor matches stricter and scores more conservative without changing the mobile API contract.

Start here:

- [Scan Engine Documentation](docs/scanning-engine.md)
- [Contribution And Moderation API Contract](docs/contribution-moderation-api.md)
- [Leaderboard Documentation](docs/leaderboard.md)

What changed:

- exact barcode resolution across multiple Open Facts databases
- product-family detection for food, cosmetics, household, pet food, and general products
- conservative scoring for sparse data
- bottled-water plastic packaging penalty
- provider-based architecture so more vendors can be added later
