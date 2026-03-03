# Security Checklist (Before GitHub Push)

Use this checklist before creating or pushing to a private GitHub repository.

## 1) Verify sensitive files are ignored

Expected to be ignored:

- `.env`
- `.venv/`
- `Inventory List with pricing.xlsx`
- `output/`

Check:

```bash
git check-ignore -v .env .venv/ "Inventory List with pricing.xlsx" output/
```

## 2) Scan repository for likely secrets

If `gitleaks` is installed:

```bash
gitleaks dir --source . --redact
```

If not installed, run a simple fallback scan:

```bash
rg -n --hidden -g '!.venv/**' -g '!.env' \
  -e 'DIGIKEY_CLIENT_SECRET|MOUSER_API_KEY|NEXAR_CLIENT_SECRET|OPENAI_API_KEY|ANTHROPIC_API_KEY|-----BEGIN'
```

## 3) Rotate any exposed credentials

If a key was ever shared in screenshots, terminal logs, or committed history:

- rotate the key
- update `.env`

## 4) GitHub repo settings (recommended)

- Private repo only
- Secret scanning + push protection enabled
- Branch protection on default branch
- Require PRs for merge
- Enforce MFA in org/user settings

## 5) Initial safe commit scope

Start by committing only:

- `scripts/`
- `dashboard/`
- `.gitignore`
- `.env.example`
- `README.md`
- `RESEARCH_AUTOMATION.md`
- `SECURITY.md`
