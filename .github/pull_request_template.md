## Step and scope
Step {N}: {title}. Closes review IDs: {IDs}.

## What changed
-

## How it was tested
- [ ] php artisan test on the dev server (paste the summary line)
- [ ] CI green (GitHub runners, not the dev server)
- [ ] A database rule test for every new trigger, constraint or policy
- [ ] Screenshots or a recording for UI changes

## Checks
- [ ] Migrations are additive (design §15.1)
- [ ] New tenant tables pass php artisan db:check-rls
- [ ] No floats in money code, no SQLite, no weakened tests
- [ ] Independent review done (/review-branch in a new conversation, different model)

## Review findings not fixed
-
