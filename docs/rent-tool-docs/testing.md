# Testing Instructions

## General Rules

- Write automated tests for every new feature and bug fix.
- Use Symfony's existing testing framework and follow the conventions of existing tests.
- Test expected behavior, validation, authentication, authorization, and relevant database changes.
- Include tests for unauthorized actions and verify that rejected requests do not modify data.
- Do not remove or weaken existing tests just to make them pass.
- Test sad paths too. For every feature, test both happy paths and relevant sad paths. Choose cases based on the feature's requirements; do not test every theoretically possible scenario. Ensure tests verify the expected outcome, not merely that the request completes.

## Running Tests

- Run relevant tests after each change.
- Run the full test suite after completing a feature.
- Report failed, skipped, or unexecuted tests honestly. Never claim tests passed unless they actually passed.


## Completion

Before reporting a feature as complete, summarize the tests run, their results, and any known gaps.
