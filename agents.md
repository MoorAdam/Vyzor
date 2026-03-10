# Agent Rules

## Documentation
- On every change, update `docs.md` to reflect the current state of the codebase. Documentation must always be up to date.
- When a new library or dependency is introduced, update the technologies list in `docs.md` immediately.

## Git
- Never run `git push` or any variant that pushes to a remote repository.

## Backend (PHP)
- Code must follow SOLID principles:
  - **S**ingle Responsibility — each class has one reason to change
  - **O**pen/Closed — open for extension, closed for modification
  - **L**iskov Substitution — subtypes must be substitutable for their base types
  - **I**nterface Segregation — prefer small, focused interfaces over large ones
  - **D**ependency Inversion — depend on abstractions, not concretions
- Follow PHP best practices: strict types, PSR standards, meaningful naming, and no magic where clarity is preferred.

## Frontend (JavaScript)
- Use the newest ECMAScript syntax and features available (ES2024+).
- Prefer native language features over external libraries where practical.
