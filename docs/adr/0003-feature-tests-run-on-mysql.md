# Feature tests run on MySQL, not in-memory SQLite

Backend tests use a real MySQL 8 database (`inkcode_testing`, see `phpunit.xml` and `docker/mysql/init/`) even though SQLite in memory would be faster. Stock reservation, overselling protection and payment idempotency depend on `SELECT ... FOR UPDATE` row locks, unique constraints and transaction isolation, which SQLite does not reproduce. A test that passes on SQLite could hide a real race condition.
