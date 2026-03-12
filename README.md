# NepaliCalendarBundle

A Symfony 6.4+ bundle that provides:

- **`CalendarService`** — PHP Bikram Sambat (BS) ↔ Gregorian (AD) date conversion  
- **Twig filters & functions** — use BS dates directly in templates  
- **Stimulus datepicker** — plug-and-play JS + CSS component that supports both AD and BS calendar modes

---

## Requirements

| Dependency            | Version    |
|-----------------------|------------|
| PHP                   | ≥ 8.1      |
| Symfony Framework     | ^6.4 / ^7  |
| Symfony Twig Bundle   | ^6.4 / ^7  |
| `symfony/cache`       | optional (recommended for caching the JSON) |

---

## Installation

```bash
composer require fintech-nepal/nepali-calendar-bundle
```

Register the bundle in `config/bundles.php` (auto-registered if Symfony Flex is active):

```php
return [
    // ...
    Danepliz\NepaliCalendarBundle\NepaliCalendarBundle::class => ['all' => true],
];
```

---

## Configuration

Create `config/packages/nepali_calendar.yaml`:

```yaml
nepali_calendar:
  # URL or absolute path to the nepali_calendar.json file
  calendar_json_path: 'https://fitnechnepal.blr1.cdn.digitaloceanspaces.com/Calendar/nepali_calendar.json'

  # Cache TTL in seconds (requires symfony/cache)
  cache_ttl: 300

  # Default locale for display: 'en' or 'ne'
  default_locale: 'en'

  # Set to false to disable the Twig extension
  register_twig_extension: true
```

> **Tip — local JSON file:** Download `nepali_calendar.json` and point `calendar_json_path` to an absolute path  
> (e.g. `%kernel.project_dir%/data/nepali_calendar.json`) for zero-latency startup.

---

## PHP Usage — CalendarService

```php
use Danepliz\NepaliCalendarBundle\Service\CalendarService;

class MyController
{
    public function __construct(private CalendarService $calendar) {}

    public function index(): Response
    {
        // AD → BS string
        $bs = $this->calendar->adToBs('2025-02-06');          // "2081-10-23"
        $bs = $this->calendar->adToBs('2025-02-06', '/', true); // "२०८१/१०/२३"

        // AD → BS array
        $arr = $this->calendar->adToBsArray('2025-02-06');
        // ['year' => '2081', 'month' => 10, 'day' => 23,
        //  'month_name' => 'Magh', 'month_name_ne' => 'माघ']

        // BS → AD
        $ad = $this->calendar->bsToAd('2081-10-23');           // "2025-02-06"
        $ad = $this->calendar->bsToAd('2081-10-23', 'd/m/Y');  // "06/02/2025"

        // Calendar helpers
        $days    = $this->calendar->daysInBsMonth(2081, 10);      // 29
        $weekday = $this->calendar->firstWeekdayOfBsMonth(2081, 1); // 6 (Saturday)

        // Nepali digits
        $this->calendar->toNepaliDigits('2025-02-06'); // "२०२५-०२-०६"

        // Fiscal month prefix
        $this->calendar->getFiscalMonthPrefix(1); // "SHR"
    }
}
```

### DateTime formatting

```php
$dt = new \DateTime('2025-02-06');
$this->calendar->formatNepaliDate($dt);     // "२३ माघ २०८१"
$this->calendar->formatNepaliDateTime($dt); // "२३ माघ २०८१ 00:00:00"
```

---

## Twig Usage

### Filters

```twig
{# DateTime object → Nepali date string #}
{{ order.createdAt | bs_date }}              {# २३ माघ २०८१ #}
{{ order.createdAt | bs_datetime }}          {# २३ माघ २०८१ 14:35:00 #}

{# AD date string → BS string #}
{{ '2025-02-06' | ad_to_bs }}               {# 2081-10-23 #}
{{ '2025-02-06' | ad_to_bs('/', true) }}    {# २०८१/१०/२३ #}

{# Convert any number string to Devanagari #}
{{ '2025' | nepali_digits }}                {# २०२५ #}
```

### Functions

```twig
{# BS → AD #}
{{ bs_to_ad('2081-10-23') }}                {# 2025-02-06 #}
{{ bs_to_ad('2081-10-23', 'd/m/Y') }}       {# 06/02/2025 #}

{# Month name arrays #}
{% for name in nepali_month_names('en') %}{{ name }}, {% endfor %}
{% for name in nepali_month_names('ne') %}{{ name }}, {% endfor %}

{# Pass calendar data directly to JS #}
<script>
  const calData = {{ nepali_calendar_data() | json_encode | raw }};
</script>
```

---

## Datepicker (Stimulus)

### 1. Import the assets

```js
// assets/app.js
import NepaliDatepickerController from '../vendor/fintech-nepal/nepali-calendar-bundle/assets/js/controllers/nepali_datepicker_controller.js';
application.register('nepali-datepicker', NepaliDatepickerController);
```

```css
/* assets/styles/app.css */
@import '../../vendor/fintech-nepal/nepali-calendar-bundle/assets/css/datepicker.css';
```

### 2. Render the widget in Twig

```twig
{# Using the bundle template #}
{% include '@NepaliCalendar/datepicker.html.twig' with {
    name: 'transaction_date',
    picker_type: 'bs',
    attr: { class: 'form-control', placeholder: 'Select date …' }
} %}
```

Or build the markup manually:

```html
<div
    data-controller="nepali-datepicker"
    data-nepali-datepicker-picker-type-value="bs"
>
    <input
        type="text"
        name="transaction_date"
        data-nepali-datepicker-target="input"
        readonly
        class="form-control"
        placeholder="Select date …"
    />
</div>
```

### Controller values

| Attribute                                           | Default    | Description                          |
|-----------------------------------------------------|------------|--------------------------------------|
| `data-nepali-datepicker-picker-type-value`          | `"ad"`     | Initial calendar mode: `"ad"` or `"bs"` |
| `data-nepali-datepicker-calendar-json-path-value`   | CDN URL    | URL or path to `nepali_calendar.json` |

### Selected value format

The input will be populated in the format:

```
2025-02-06 AD (२०८१-१०-२३ BS)
```

Parse it server-side with `CalendarService::adToBs()` or `bsToAd()` as needed.

---

## Tests

```bash
composer install
./vendor/bin/phpunit tests/
```

---

## calendar.json format

The JSON file must follow this structure (keyed by BS year string):

```json
{
  "2081": [
    [1,  "2024-04-13", 31],
    [2,  "2024-05-14", 31],
    ...
    [12, "2025-03-13", 30]
  ]
}
```

Each month entry: `[month_number, "AD_start_date", days_in_month]`

---

## License

MIT
