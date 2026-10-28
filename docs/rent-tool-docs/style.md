# Style Guidelines

## Visual Direction

- Build a modern, polished, professional SaaS interface for a power-tool rental management application.
- Make the application feel dynamic, responsive, and visually engaging rather than static or generic.
- Use animations, transitions, and interactive visual feedback extensively to create a premium, high-quality user experience.
- Maintain clear visual hierarchy, readability, consistency, and a professional appearance.
- Use a coherent visual identity across inventory, customers, rentals, and rental history, and for generated contract and invoice PDFs.
- Avoid excessive decoration that interferes with usability, but do not sacrifice visual interest in favor of an unnecessarily minimal interface.

## Design System

- Centralize colors, typography, spacing, borders, radii, shadows, transitions, animations, and component dimensions in reusable design tokens.
- Define a consistent color palette, with distinct colors for primary actions, secondary actions, success, warnings, errors, and informational states.
- Establish consistent typography, sizing, content widths, responsive breakpoints, and spacing rules.
- Use subtle shadows, layered surfaces, borders, and background variations to establish depth and hierarchy.
- Use Tailwind CSS utilities and existing project conventions wherever appropriate.
- Prefer CSS transitions and animations for straightforward interactions. Use JavaScript or Alpine.js only when an interaction requires additional behavior.
- Avoid introducing unnecessary styling or animation dependencies.

## Components

- Build reusable buttons, form controls, cards, tables, badges, dialogs, dropdowns, navigation elements, notifications, and page layouts.
- Ensure that shared components have consistent styling, sizing, behavior, and animation patterns.
- Apply appropriate hover, focus, active, disabled, loading, success, and error states to interactive components.
- Use visual indicators to communicate meaningful application states, such as tool availability, active rentals, and overdue returns.
- Make inventory items, actionable table rows, and navigation elements feel interactive wherever appropriate.
- Provide consistent loading, empty, error, confirmation, and validation states.
- Use clear visual distinctions between destructive actions, such as deleting records, and ordinary actions.

## Animations and Interactions

- Animations and interactive feedback are a core design requirement, not optional enhancements. Apply them consistently throughout the application.
- Every interactive element should provide appropriate visual feedback when hovered, focused, clicked, activated, or disabled.
- Use smooth transitions for color, background, border, shadow, opacity, and transform changes.
- Buttons should have noticeable but restrained hover effects, such as color transitions, subtle elevation, shadow changes, or small movement.
- Cards should respond to hover with effects such as a slight lift, enhanced shadow, border-color change, or subtle background transition.
- Navigation items should have animated hover indicators, background transitions, or other clear feedback that reinforces the current interaction.
- Table rows should provide smooth hover feedback to make records easier to track visually. Interactive rows should clearly communicate that they can be opened or selected.
- Form controls should transition smoothly between default, hover, focus, validation, and disabled states.
- Dropdowns, dialogs, popovers, and notifications should use appropriate entrance and exit animations, such as fading, sliding, or scaling.
- Animate expanding and collapsing sections, dropdown menus, and other elements whose visibility changes.
- Use subtle staggered entrance animations for groups of cards or list items when they improve the overall experience.
- Use animated loading indicators and skeleton screens where appropriate.
- Provide clear animated feedback for important actions, such as successfully saving a customer, creating a rental, updating inventory, or generating an invoice.
- Use animations to communicate changes in application state, including status changes, newly added records, and validation errors.
- Keep transitions smooth and intentional. Avoid excessive bouncing, distracting looping animations, large movements, or effects that slow down routine business tasks.
- Avoid animating every element continuously. Prioritize animations triggered by user interaction or meaningful state changes.
- Respect the user's reduced-motion accessibility preference and provide alternatives to nonessential motion.

## Page-Specific Guidance

- **Inventory:** Make tool cards, availability indicators, and inventory tables responsive to user interaction. Support search and status filtering.
- **Customers:** Use consistent interactive table rows, searchable lists, clear customer details, and animated feedback for actions.
- **Rentals:** Make rental statuses, date information, return actions, and overdue indicators immediately recognizable. Use animations to reinforce status changes and important actions.
- **Rental History:** Maintain a readable, structured presentation with smooth filtering, sorting, pagination, and record-selection feedback.
- **Generated PDFs:** Present contract and invoice details with a clear hierarchy, readable content, and euro-denominated totals.
- Keep all page-specific styling consistent with the shared design system.

## Performance and Accessibility

- Keep animations smooth and responsive without introducing unnecessary rendering work.
- Prefer animating performant CSS properties, especially `transform` and `opacity`, when suitable.
- Avoid animations that cause unnecessary layout shifts or make tables, forms, and navigation feel sluggish.
- Ensure keyboard accessibility, semantic HTML, visible focus indicators, sufficient color contrast, and appropriately sized interactive targets.
- Never rely on color or animation alone to communicate essential information.
- Ensure that essential functionality remains available when animations are disabled or reduced.

## Rules

- Follow these guidelines for every new or modified page and component.
- Prefer shared design tokens and reusable components over hardcoded values and duplicated styles.
- Reuse existing project dependencies and established conventions before introducing new ones.
- Do not add arbitrary, inconsistent styling or one-off animation patterns.
- Do not compromise usability, accessibility, or application performance for visual effects.
- Treat hover effects, transitions, and meaningful interaction feedback as mandatory wherever appropriate, rather than optional polish.
- When introducing a new component or interaction, define its visual states and animation behavior consistently with the existing system.
- Update this guide when established styling conventions change.


## Symfony Forms and Validation

- Use **Symfony Forms** for all application forms, including data binding, CSRF protection, and integration with Symfony Validator. Define appropriate validation constraints on entities or dedicated validation models.
- Use Symfony's built-in form handling and validation workflow, including automatic display of field-level validation errors. Do not manually reimplement form processing, validation, or error handling.
- Use Symfony's Twig form rendering functions (`form_start()`, `form_row()`, `form_widget()`, `form_errors()`, etc.) and preserve automatic error display and submitted values when validation fails.
- Style forms using **Tailwind CSS, custom CSS, reusable design tokens, and shared form themes**. Maintain consistent styling for inputs, labels, help text, buttons, and validation states across the application.
- Provide clear visual feedback for invalid fields, validation messages, focus, disabled states, and required fields. Ensure accessibility, responsive layouts, and consistent transitions.
- Client-side validation may enhance usability, but **server-side Symfony validation remains authoritative**. Never rely exclusively on JavaScript or browser validation.
- Prefer reusable form themes and shared styling conventions over duplicated field-specific configuration. Do not introduce additional form libraries or styling dependencies without a clear benefit.
