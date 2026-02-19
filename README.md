

go-garbage-grabber-website
This repository contains a full backup of the Go Garbage Grabber WordPress site (files + database export).

IMPORTANT

DO NOT commit wp-config.php or any file containing API credentials (Stripe secret keys, etc.). wp-config.php is already moved to backups/ and .gitignore excludes sensitive files.
Large archives and DB dumps are ignored by .gitignore to avoid accidental pushes.
Contents (local)

backups/ # Database export(s) and original wp-config.php
gogarbagegrabber_files_backup_*.zip # Extracted site archive (ignored)
wp-content/ (extracted inside the archive)
README.md
LICENSE
How to push to GitHub (example)

Ensure you are in the project root: cd ~/projects/go-garbage-grabber-website
Create the remote repository on GitHub (web UI or gh CLI)
gh repo create Toddchild/go-garbage-grabber-website --public --source=. --remote=origin --push or create it via github.com → New repository
If you used the web UI, run: git remote add origin git@github.com:Toddchild/go-garbage-grabber-website.git git branch -M main git push -u origin main EOF cat > LICENSE <<'EOF' MIT License
Copyright (c) 2026 Toddchild

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction... EOF git add README.md LICENSE git commit -m "Add README and LICENSE" || true git status --short --branch
