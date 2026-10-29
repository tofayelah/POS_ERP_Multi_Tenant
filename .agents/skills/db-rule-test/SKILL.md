---
name: db-rule-test
description: Write Pest tests proving that a PostgreSQL trigger, constraint, RLS policy or composite key rejects every forbidden change. Use when adding or changing any database rule.
---
# Database rule tests

For the rule the user names:
1. Read its SQL and list every way a row could break it: insert; update of each
   protected column; delete; moving a child row to another parent; references
   across tenants or companies; and the same changes made through a status change.
2. For each forbidden path, write one test in tests/Database/{Rule}Test.php that
   expects Illuminate\Database\QueryException and checks the SQLSTATE or message,
   so a different error doesn't count as a pass. Add a test for each allowed path,
   so the rule isn't simply rejecting everything.
3. Tests connect as app_rw on PostgreSQL 17 with tenant context set through
   TenantContext. Never use SQLite or the owner connection.
4. Put the review ID each test guards in its description, for example "(NEW-27)".
5. Commit, push, pull on the dev server and run the tests there. If the rule
   isn't built yet, show them failing first.
