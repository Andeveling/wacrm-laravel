CREATE EXTENSION IF NOT EXISTS vector;

-- Nothing in the stack — PHPUnit, Pest, Laravel, PDO, Postgres — puts a
-- deadline on a blocked query. So when two things touch `testing` at once
-- (two test runs, or a connection left behind by a killed one), the
-- `drop table` that RefreshDatabase issues waits forever and the suite
-- looks frozen with no output and no diagnostic.
--
-- lock_timeout turns that silent hang into a readable error in seconds:
-- no legitimate test waits 10s for a lock. idle_in_transaction_session_timeout
-- reaps the holder itself, so a killed run cannot poison the next one. A
-- Browser test legitimately sits idle inside its transaction while
-- Playwright drives the page, but never for two minutes between queries.
ALTER ROLE wacrm SET lock_timeout = '10s';
ALTER ROLE wacrm SET idle_in_transaction_session_timeout = '120s';
