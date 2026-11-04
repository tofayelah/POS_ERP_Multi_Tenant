---
name: review-branch
description: Independently review the current branch against AGENTS.md, the step map, the design and the latest design review. Use in a new conversation before opening a pull request.
---
# Review this branch

Act as a reviewer who didn't write this code.
1. Run git diff main...HEAD here and read the whole diff.
2. Find the step from the branch name in docs/build/steps.md and read the design
   sections and review IDs it lists.
3. Check tenant isolation, immutability of financial records, money handling,
   concurrency and lock order, missing tests for forbidden paths, non-additive
   migrations, secrets, and anything the design or review requires that's missing.
4. You may run the tests on the dev server. Don't change any files, here or there.

Reply with a table: severity (blocker, major, minor), file:line, problem, fix.
Finish with each review ID the step claims to close, marked closed, partly
closed or open.
