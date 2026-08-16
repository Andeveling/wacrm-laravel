# Frontend screen modules

A **screen module** is the frontend home of one product surface. WhatsApp is the reference. Contacts and accounts already follow the same shape.

## When this fires

Creating or splitting a large Inertia screen, adding a settings surface that will grow past one file, or naming types for a page that already has a product module.

## Shape

```
resources/js/modules/<product>/
  contracts.ts      # Inertia prop types
  model.ts          # pure rules
  model.test.ts     # Vitest, only if the rules are real
  use-<product>-*.ts
  ui/
    <product>-screen.tsx
    <piece>.tsx
resources/js/pages/<inertia-path>.tsx   # reexport only
```

Name the module after the product (`whatsapp`), not after Settings and not after the PHP namespace.

## What each file owns

| File | Owns | Does not own |
| --- | --- | --- |
| `contracts.ts` | Connection, readiness, issue, page props | HTTP verbs, copy |
| `model.ts` | STEP_ORDER, labels, `hasActiveDefault`, kind → variant | Inertia, React |
| hook | one `busyId` mutex, visits, dialog target | form fields, markup |
| `*-screen.tsx` | heading, flash toasts, which pieces mount | business rules |
| pieces | markup, local `useId()`, local widget state | sibling mutations |

The connect form is the exception: it owns `useForm` and submits the connect action. Retry fills that same form.

## Composition

Pieces take props and children. Read-only is “do not mount write UI”: no form, no card actions, no remediation writes. Connection cards do not take a `canManage` boolean; the screen passes actions as children.

One page does not get `createContext` / Provider. Inbox already composes this way.

## Identifiers

New TypeScript says the product word: **Remediation**, not Legacy. The Inertia prop `legacyIssues` and existing `data-testid`s stay at the wire. PHP models keep their names.

Each form piece calls `useId()` for `htmlFor` / `id`. `name` attributes stay as the browser journey expects. A select does not use a row UUID as its DOM id.

## Tests

A good test asserts operator-visible behavior or a pure function. Feature HTTP / Inertia and Browser journeys stay the product contract. Vitest covers the model only. Do not add Testing Library / jsdom for a split (ADR 0004).

Other screens adopt this shape when next touched (ADR 0001 gradual rule; ADR 0009).
