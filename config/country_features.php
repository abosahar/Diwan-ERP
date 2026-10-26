<?php

/*
 * Features that exist only in some countries. A feature is on when the branch
 * being worked in matches its rule: `countries` (country codes), `einvoicing`
 * (government e-invoicing systems of config/countries.php), `has_tax` (the
 * country charges a general VAT / sales tax) or `national_address`.
 * `routes` are route names or prefixes ("zatca." ) the server refuses when the
 * feature is off, so a hidden menu item cannot be reached by URL either.
 */
return [
    'zatca' => ['einvoicing' => ['zatca'], 'routes' => ['zatca.', 'settings.zatca.']],
    'zakat' => ['countries' => ['SA'], 'routes' => ['accounting.period-end.zakat']],
    'gosi' => ['countries' => ['SA'], 'routes' => ['hr.reports.gosi']],
    'wps' => ['countries' => ['SA'], 'routes' => []],
    'saudization' => ['countries' => ['SA'], 'routes' => ['hr.reports.saudization']],
    'vat_return' => ['has_tax' => true, 'routes' => ['accounting.reports.vat-return']],
    'national_address' => ['national_address' => true, 'routes' => []],
];
