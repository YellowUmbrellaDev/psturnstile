set shell := ["bash", "-eu", "-o", "pipefail", "-c"]

MODULE := "psturnstile"
BUILD_DIR := "build"
VERSION := env_var_or_default("VERSION", "")

# Show available commands.
default:
	@just --list

# Show repository status.
status:
	git status --short --branch
	@if [ -d "{{BUILD_DIR}}" ]; then \
		printf '\nBuild artifacts:\n'; \
		ls -lh "{{BUILD_DIR}}"; \
	fi

# Validate Composer metadata strictly.
validate:
	composer validate --strict

# Regenerate an optimized Composer autoloader.
dump-autoload:
	composer dump-autoload --optimize

# Lint module PHP files, excluding bundled Composer dependencies.
lint:
	@echo "Linting PHP files in repository module root..."
	@count=0; fail=0; \
	while IFS= read -r -d '' file; do \
		if ! php -l "$file" >/dev/null 2>&1; then \
			php -l "$file"; \
			fail=1; \
		fi; \
		count=$((count + 1)); \
	done < <(find . -type f -name '*.php' \
		-not -path './vendor/*' \
		-not -path './build/*' \
		-not -path './.git/*' \
		-not -path './.agents/*' \
		-not -path './.opencode/*' \
		-print0); \
	if [ "$count" -eq 0 ]; then \
		echo "No PHP files found."; \
		exit 0; \
	fi; \
	if [ "$fail" -ne 0 ]; then \
		echo "Lint errors found." >&2; \
		exit 1; \
	fi; \
	echo "Linted $count PHP files."

# Remove and recreate the build directory.
clean-build:
	rm -rf "{{BUILD_DIR}}"
	mkdir -p "{{BUILD_DIR}}"

# Validate optional VERSION used in local package filenames.
validate-version:
	@if [ -n "{{VERSION}}" ] && ! [[ "{{VERSION}}" =~ ^[0-9A-Za-z._-]+$ ]]; then \
		echo "VERSION may only contain letters, numbers, dots, underscores, and hyphens." >&2; \
		exit 1; \
	fi

# Create build/psturnstile.zip, or psturnstile-<VERSION>.zip when VERSION is set.
package: clean-build validate-version
	@command -v zip >/dev/null || { echo "zip is required but was not found in PATH." >&2; exit 127; }
	@command -v rsync >/dev/null || { echo "rsync is required but was not found in PATH." >&2; exit 127; }
	@mkdir -p "{{BUILD_DIR}}/{{MODULE}}"
	@rsync -a \
		--exclude='.git/' \
		--exclude='.github/' \
		--exclude='.agents/' \
		--exclude='.opencode/' \
		--exclude='build/' \
		--exclude='dist/' \
		--exclude='node_modules/' \
		--exclude='tests/' \
		--exclude='.gitignore' \
		--exclude='justfile' \
		--exclude='skills-lock.json' \
		--exclude='.phpunit.result.cache' \
		./ "{{BUILD_DIR}}/{{MODULE}}/"
	@archive="{{MODULE}}.zip"; \
	if [ -n "{{VERSION}}" ]; then archive="{{MODULE}}-{{VERSION}}.zip"; fi; \
	echo "Creating {{BUILD_DIR}}/${archive}..."; \
	(cd "{{BUILD_DIR}}" && zip -rq "${archive}" "{{MODULE}}"); \
	echo "Created {{BUILD_DIR}}/${archive}"

# Run validation, linting, and packaging.
check: validate lint package

# Run CodeRabbit review for the current repository changes.
coderabbit-review:
	@command -v coderabbit >/dev/null || { echo "coderabbit CLI is required. Install it from https://www.coderabbit.ai/cli" >&2; exit 127; }
	coderabbit review --agent
