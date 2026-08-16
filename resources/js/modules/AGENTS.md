## Common & modules
Both common and modules contain one level of directories to define contexts. Their contents can be freely determined based on the context’s needs. However, when a directory grows in size, we recommend organizing it by type.

A common module like button or card might only contain a few top-level components.

resources
└── js
    └── modules
        └── agenda
            ├── components
            │   ├── agenda.tsx
            │   ├── list-view.tsx
            │   └── grid-view.tsx
            ├── contexts
            │   └── agenda-context.tsx
            ├── helpers
            │   └── parse-date.ts
            ├── hooks
            │   └── use-agenda.ts
            └── types.ts

Subdirectories by type
Typical subdirectories to organize a module would be:

components
contexts
constants
helpers
hooks
stores

We usually group all of a module's type definitions in a single types.ts, but if it grows too large, we'll introduce a types directory.

constants, helpers, and hooks can exist at the top-level of the common directory for low-level utilities.
