---
trigger: always_on
---
# Working with the dev server

You edit code in this local folder. It runs on the dev server, over SSH: PHP,
Composer, Node, Go, Docker, PostgreSQL and Redis are installed there, not here.
The dev server is Proxmox container 104 (dev.kcerp.com), reached as
ssh erp-dev through a jump user on the Proxmox host. The user gives the details
(ssh alias, project folder, branch, test database) in the first message of each
conversation. If they're missing, ask. Never connect to the Proxmox host
(kcerp-prox) or any other container unless the prompt says so.
Never ask for, read or print a private key, passphrase or password, never read
~/.ssh, and never print or copy a .env file. When a prompt asks you to change
one non-secret setting in the server's .env, use sed without displaying it.

## Running commands on the server
- Form: ssh {alias} 'cd {project folder} && {command}'
- One command per ssh call, so the user can review each one. Never append a
  second, unrelated command with ; or &&.
- Run php, composer, artisan, npm, npx, go, docker and psql only on the server.
- Never run migrate:fresh, db:wipe, docker compose down -v, git reset --hard,
  git clean or rm -rf on the server unless the user asks in this conversation.

## Keeping both copies in step (Git is the only way code moves)
1. Before running code on the server: commit here, git push, then on the server
   git fetch, git switch {branch} and git pull --ff-only.
2. If git status on the server isn't clean before a pull, stop and report it.
   Don't stash, reset or discard anything there.
3. Commands that create or change files run on the server (composer require,
   npm install, php artisan make:*, boost:install, boost:update, pint without
   --test). Afterwards, commit those files on the server, push, and run
   git pull --ff-only here before editing anything else.
4. Never edit the same file in both places without syncing in between.
5. Never push to main, and never force-push, from either side.

## Seeing results
- The app runs in the container on 0.0.0.0:8080, in a tmux session named erp
  (composer serve:dev and php artisan queue:listen, after npm run build). The
  reverse proxy publishes it as https://dev.kcerp.com; open that in the browser.
- To look at a file the server produced (a screenshot, a PDF, a k6 summary),
  copy it here with scp into .scratch/, which Git ignores.
