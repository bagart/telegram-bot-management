# Task: Generic web settings renderer for module settings screens

Context: `telegram-bot-platform/docs/tasks/module-engine/03-settings-screens-contribution.md`
(architecture decision — read it first).

Management stays module-agnostic: it renders whatever settings screens the
engine resolves for a bot, and never holds a module list.

## Scope

- [ ] Generic Inertia/React settings form driven by the engine's
      `SettingsDescriptor` (typed PHP-DTO fields: IntField, EnumField,
      BoolField, StringField, …) — no per-module controllers.
- [ ] Screen listing page: resolved `SettingsScreenContribution`s for a
      selected bot, filtered by module activation (engine availability API).
- [ ] Access: Platform Administration vs Bot Administration level per the
      decision doc; server-side enforcement (visibility ≠ authorization).
- [ ] Save path: values persisted per scope (bot → chat where applicable)
      through the engine's settings storage contract; optimistic concurrency
      via expected_revision (see EXTRACTED-IDEAS.md, Configuration section).
- [ ] Custom `WebScreenBinding` escape hatch: mount a module-provided
      Inertia page/component inside the Management shell (lazy chunk import).
- [ ] i18n: field labels/descriptions via keys, five languages (RU/EN/FR/ES/ZH).

## Out of scope

- Telegram-side rendering (menu module's task 25); settings contracts and
  storage (module-engine task 03a).
