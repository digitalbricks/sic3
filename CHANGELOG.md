# SIC 3 changelog
(since SIC 3.2.0)

## 3.4.1, 27.08.2026
* added explicit $escape parameter on calls to `fputcsv`, `fgetcsv`, `str_getcsv` to prevent errors under PHP 8.4 (See https://php.watch/versions/8.4/csv-functions-escape-parameter)

## 3.4.0, 16.06.2026
* added addon system
* versioncheck addon by "Slugger" included

## 3.3.4, 04.04.2025
* handling of cases where satellite does not answer with correct JSON (e.g. because of PHP errors)

## 3.3.3, 26.08.2024
* added very basic print stylesheet

## 3.3.2, 26.04.2024
* improvements for SIC usage inside a subdirectory

## 3.3.1, 26.04.2024
* some color fixes

## 3.3.0, 26.04.2024
* added changelog output in `/update` route
* some minor bugfixes and improvements

## 3.2.0, 25.04.2024
* added update checker
* prepared route for (scheduled) web updater