# Asynchronous File Fetching

The OpenConnector SynchronizationService now supports asynchronous file fetching using ReactPHP for improved performance during synchronization operations. This feature allows file downloads to happen in the background without blocking the main synchronization process.

## Overview

When a synchronization rule includes file fetching operations, these can be time-consuming and may slow down the overall synchronization process. The asynchronous file fetching feature addresses this by:

- **Fire-and-forget execution**: File fetching operations are initiated but don't block the synchronization
- **Immediate continuation**: Synchronization continues with placeholder values while files are fetched in the background
- **Error isolation**: File fetching errors don't affect the main synchronization process
- **ReactPHP integration**: Uses ReactPHP promises and event loop for efficient async operations

## How It Works

### 1. Rule Processing

When the `processFetchFileRule` method is called during synchronization:

1. **Validation**: Checks if OpenRegister app is available and configuration is valid
2. **Endpoint extraction**: Extracts file endpoints from the data using the configured file path
3. **Async initiation**: Starts asynchronous file fetching operations
4. **Placeholder return**: Returns immediately with placeholder values

### 2. Asynchronous Execution

The async file fetching process involves several steps:

```php
// 1. Schedule async operation
$loop->futureTick(function() use ($source, $config, $endpoint, $objectId, $ruleId) {
    $this->executeAsyncFileFetching($source, $config, $endpoint, $objectId, $ruleId);
});

// 2. Execute file fetching based on endpoint type
switch ($this->getArrayType($endpoint)) {
    case 'Not array':
        $this->fetchFileAsync($source, $endpoint, $config, $objectId);
        break;
    // ... other cases
}

// 3. Wrap in ReactPHP promise
$deferred = new Deferred();
$result = $this->fetchFile(/* parameters */);
$deferred->resolve($result);
```

### 3. Endpoint Types Supported

The system handles different types of file endpoints:

- **Single endpoint**: Direct file URL
- **Associative array**: Object containing file metadata and endpoint
- **Multidimensional array**: Array of objects with file information
- **Indexed array**: Simple array of file endpoints

## Configuration

### Rule Configuration

File fetching rules are configured in the synchronization rule configuration:

```json
{
  'fetch_file': {
    'source': 'source-id',
    'filePath': 'path.to.file.endpoint',
    'write': true,
    'autoShare': false,
    'tags': ['document', 'attachment'],
    'sourceConfiguration': {
      'headers': {
        'Authorization': 'Bearer token'
      }
    }
  }
}
```

### Configuration Options

- **source**: ID of the source to fetch files from
- **filePath**: Dot notation path to the file endpoint in the data
- **write**: Whether to write files to storage (default: true)
- **autoShare**: Whether to automatically share files (default: false)
- **tags**: Array of tags to assign to fetched files
- **sourceConfiguration**: Additional configuration for the HTTP request

## Placeholder Values

While files are being fetched asynchronously, the synchronization continues with placeholder values:

- **Single file**: `'file://fetching-async'`
- **Multiple files**: Array filled with `'file://fetching-async'` placeholders

These placeholders:
- Maintain the expected data structure
- Allow synchronization to continue without errors
- Can be identified and handled by downstream processes

## Error Handling

The asynchronous file fetching includes comprehensive error handling:

### Non-blocking Errors

File fetching errors are logged but don't affect the main synchronization:

```php
try {
    $result = $this->fetchFile(/* parameters */);
    $deferred->resolve($result);
} catch (Exception $e) {
    error_log('File fetch failed for endpoint {$endpoint}: ' . $e->getMessage());
    $deferred->reject($e);
}
```

### Error Scenarios

- **Source not found**: Logs error and continues with original data
- **Network failures**: Logged but don't block synchronization
- **File processing errors**: Isolated to individual file operations
- **Configuration errors**: Throw exceptions only for critical misconfigurations

## Performance Benefits

### Before Async Implementation

```
Synchronization Process:
├── Process Object 1
│   ├── Fetch File 1 (2s)
│   ├── Fetch File 2 (3s)
│   └── Continue processing (1s)
├── Process Object 2
│   ├── Fetch File 3 (2s)
│   └── Continue processing (1s)
└── Total: ~9 seconds
```

### After Async Implementation

```
Synchronization Process:
├── Process Object 1
│   ├── Start async fetch (File 1, File 2)
│   └── Continue processing (1s)
├── Process Object 2
│   ├── Start async fetch (File 3)
│   └── Continue processing (1s)
└── Total: ~2 seconds (files fetch in background)
```

## ReactPHP Integration

### Dependencies

The implementation uses several ReactPHP components:

```php
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use function React\Promise\resolve;
```

### Event Loop Usage

The ReactPHP event loop is used to schedule asynchronous operations:

```php
$loop = Loop::get();
$loop->futureTick(function() {
    // Async file fetching logic
});
```

### Promise Pattern

Each file fetch operation is wrapped in a ReactPHP promise:

```php
private function fetchFileAsync(/* parameters */): PromiseInterface
{
    $deferred = new Deferred();
    
    try {
        $result = $this->fetchFile(/* parameters */);
        $deferred->resolve($result);
    } catch (Exception $e) {
        $deferred->reject($e);
    }
    
    return $deferred->promise();
}
```

## Best Practices

### 1. Configuration

- Always specify appropriate tags for file organization
- Use meaningful source configurations
- Set reasonable timeouts for file operations

### 2. Error Handling

- Monitor error logs for file fetching issues
- Implement retry mechanisms for critical files
- Use placeholder detection in downstream processes

### 3. Performance

- Consider file sizes when designing synchronization rules
- Use appropriate batch sizes for large file sets
- Monitor system resources during heavy file operations

### 4. Testing

- Test with various endpoint types
- Verify placeholder handling in target systems
- Test error scenarios and recovery

## Monitoring and Debugging

### Log Messages

The system logs various events for monitoring:

```
Failed to find source for fetch file rule: [error message]
Async file fetching failed for rule [rule-id]: [error message]
File fetch failed for endpoint [endpoint]: [error message]
```

### Debugging Tips

1. **Check source configuration**: Ensure the source ID exists and is accessible
2. **Verify file paths**: Confirm the filePath configuration points to valid data
3. **Monitor network**: Check for connectivity issues to external sources
4. **Review logs**: Look for patterns in error messages
5. **Test endpoints**: Manually verify file endpoints are accessible

## Migration from Synchronous

If migrating from synchronous file fetching:

1. **Update expectations**: Downstream processes should handle placeholder values
2. **Monitor performance**: Verify the expected performance improvements
3. **Test thoroughly**: Ensure all file types and configurations work correctly
4. **Update documentation**: Inform users about the new asynchronous behavior

## Future Enhancements

Potential improvements to the async file fetching system:

- **Progress tracking**: Monitor file fetch progress
- **Retry mechanisms**: Automatic retry for failed downloads
- **Batch optimization**: Intelligent batching based on file sizes
- **Caching**: Cache frequently accessed files
- **Compression**: Compress files during transfer
- **Parallel limits**: Configurable limits on concurrent downloads 