# Laravel Product Import & Image Processing

## Overview

This Laravel project demonstrates:

* CSV **product import** with **upsert logic**.
* **Image processing** with automatic variant generation.
* Unit tests validating both functionalities.

---

## Requirements

* PHP >= 8.1
* Composer
* MySQL or other supported database
* Laravel >= 10.x

---

## Installation

1. **Clone the repository**

```bash
git clone https://github.com/dharti-patel/hipster-image-upload.git
cd wpoets-task
```

2. **Install dependencies**

```bash
composer install
```

3. **Copy `.env` and configure database**

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials.

4. **Run migrations**

```bash
php artisan migrate
```

---

## Usage

### Product Import

* The `ProductImportService` handles CSV imports with upsert logic.
* Example CSV headers:

```
sku,name,price,description
```

* Duplicate SKUs are updated, invalid rows are skipped.

### Image Processing

* The `ImageProcessingService` handles uploaded images:

  * Generates 256px, 512px, 1024px variants.
  * Verifies checksum before processing.

* Uses `Storage::fake()` in tests for isolation.

---

## Running Tests

### Run all tests

```bash
php artisan test
```

### Run only unit tests

```bash
php artisan test --testsuite=Unit
```

### Run only required tests (upsert + image processing)

```bash
php artisan test --filter=ProductUpsertTest --filter=ImageProcessingTest --colors=always
```

* Tests include:

1. `ProductUpsertTest` → Validates CSV import and upsert logic.
2. `ImageProcessingTest` → Validates image processing and variant generation.
