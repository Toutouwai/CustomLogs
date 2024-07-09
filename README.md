# Custom Logs

When you use the core `$log->save()` method you can only save a single string of text. When you view the log in the core ProcessLogger the columns and their header labels are predetermined.

The Custom Logs module is different in that it that lets you write and view log files with the number of columns and the column header labels you specify in the module configuration.

## Configuration

In the "Custom logs" textarea field, enter custom logs, one per line, in the format...
```
name: column label, column label, column label
```
...with as many comma-separated column labels as needed.

The log name must be a word consisting of only `[-._a-z0-9]` and no extension.

If you prefix a URL column label with `{url}` then the value in the column will be rendered as a link in the log viewer.

The date/time will automatically be added as the first column so you do not need to specify it here.

Example of a configuration line:
```
my-log: {url}URL, IP Address, User Agent, Details
```

## Writing to a custom log

Use the `CustomLogs::save($name, $data, $options)` method to save data to a custom log file.

```php
$cl = $modules->get('CustomLogs');
$cl->save('my-log', $my_data);
```

### Arguments

`$name` Name of log to save to (word consisting of only `[-._a-z0-9]` and no extension).

`$data` An array of strings to save to the log. The number and order of items in the array should match the columns that are configured for the log.

`$options` (optional) Options for [FileLog::save()](https://processwire.com/api/ref/file-log/save/). Normally you won't need to use this argument.

## Example of use

Custom log definition in the module configuration:

```
visits: {url}URL, IP Address, User Agent, {url}Referrer
```

Saving data to the log:

```php
$cl = $modules->get('CustomLogs');
$data = [
    $_SERVER['REQUEST_URI'] ?? '',
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    $_SERVER['HTTP_REFERER'] ?? '',
];
$cl->save('visits', $data);
```

Viewing the resulting log in Setup > Logs > visits:

![custom-logs-1](https://github.com/Toutouwai/CustomLogs/assets/1538852/9977c29d-9b5c-4f0a-aa45-cb02086a2b78)