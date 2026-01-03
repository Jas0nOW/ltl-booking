# Contributing to LazyBookings

**Scope:** Guidelines for code contributions, branch management, and coding standards.  
**Non-Scope:** General WordPress development or plugin usage.

## Who should read this?
- Developers wanting to contribute to the project.

---

## 1. Development Workflow

### Branching Strategy
- `main`: Stable production-ready code.
- `develop`: Integration branch for new features.
- `feature/*`: Individual feature branches.
- `fix/*`: Bug fix branches.

### Pull Requests
1. Fork the repository.
2. Create a feature branch from `develop`.
3. Implement your changes following the [Coding Standards](#2-coding-standards).
4. Submit a PR to the `develop` branch.
5. Ensure all tests pass and documentation is updated.

---

## 2. Coding Standards

### PHP
- Follow **PSR-12** coding standards.
- Use strict typing (`declare(strict_types=1);`) where possible.
- Always use explicit casting for string functions (e.g., `strpos((string)$var, '...')`) to ensure PHP 8.1+ compatibility.
- Use `$wpdb->prepare()` for all database queries.

### CSS
- Use the **Design System** tokens (`assets/css/tokens.css`).
- Follow the BEM naming convention for new components.
- Run `npm run build` to verify CSS compilation.

### Documentation
- Update relevant `.md` files in the `docs/` folder.
- Follow the **Diátaxis** structure.
- Include **Scope & Non-Scope** at the beginning of each document.

---

## 3. Testing

- Run manual smoke tests for any UI changes.
- Use the **Diagnostics** page to verify system integrity.
- (Planned) Run PHPUnit tests for core logic.

---

## Next Steps
- [Architecture Overview](docs/architecture.md)
- [Testing Guide](docs/how-to/testing.md)
- [Design Guide](docs/explanation/design-guide.md)
