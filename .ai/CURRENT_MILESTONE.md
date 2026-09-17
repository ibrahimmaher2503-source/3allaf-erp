# Current Milestone — Egyptian Feed Store ERP

**Date:** 2026-09-17
**Status:** P0.9 COMPLETE locally; P1 remains blocked until the full-day MariaDB end-to-end gate passes.

P0.9 feed-store pricing is implemented and verified on dedicated MariaDB `rajeh_p0_9_feed_pricing_20260917`. Price precedence is customer special, customer list, legacy approved store price for the base unit, then outlet/default List 0. Prices and minimums are exact per selling unit; approved sale lines retain immutable source snapshots.

Authenticated browser UAT remains manual. Production deployment, migration, release packaging, activation, push, and tag are outside this milestone.
