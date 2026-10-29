---
name: erp-step
description: Start or resume a numbered build step of the retail ERP, for example "step 4" or "/erp-step 4". Reads the step map, design sections and review items, and plans before coding.
---
# Run an ERP build step

1. Read AGENTS.md, .agents/rules/dev-server.md and the entry for the requested
   step in docs/build/steps.md.
2. Read the listed sections of docs/design/ERP_SYSTEM_DESIGN.md and the listed
   IDs in docs/reviews/ERP_DESIGN_REVIEW_v1.5.0.md. Where they disagree, the
   review wins; say so.
3. Check that the server has the step's branch checked out with a clean status.
4. If docs/build/step-NN-plan.md exists, show its unticked commits and continue
   with the first one. Otherwise report any open gate items, write the plan
   (goal, review IDs, commits in order with the test written first for each,
   migrations with their rule tests, commands that must run on the server,
   risks) and stop for approval.
5. For each commit: write the failing test, commit, push, pull on the server and
   run it; implement, commit, push, pull and run again; then run Pint, PHPStan
   and the affected tests on the server, and tick the commit in the plan.
6. Stop after each commit and show the test output.
