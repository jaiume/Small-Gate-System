<?php
/**
 * Global Error Handler for EntryZen System
 * Catches all uncaught errors and exceptions and displays them in a user-friendly format
 * that can be easily copied and sent to the developer.
 */

// Store original error reporting level
$original_error_reporting = error_reporting();

// Set error reporting to catch all errors
error_reporting(E_ALL);

// Custom error handler for PHP errors
function entryzen_error_handler($errno, $errstr, $errfile, $errline) {
    // Don't handle errors that are suppressed with @
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // Convert error to exception for consistent handling
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

// Custom exception handler for uncaught exceptions
function entryzen_exception_handler($exception) {
    // Clear any output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Get error details
    $error_details = entryzen_format_error($exception);
    
    // Log the error
    error_log("EntryZen Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Display error page
    entryzen_display_error_page($error_details);
    exit(1);
}

// Shutdown function to catch fatal errors
function entryzen_shutdown_handler() {
    $error = error_get_last();
    
    // Only handle fatal errors
    $fatal_errors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    
    if ($error !== null && in_array($error['type'], $fatal_errors)) {
        // Clear any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $error_details = entryzen_format_fatal_error($error);
        
        // Log the error
        error_log("EntryZen Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Display error page
        entryzen_display_error_page($error_details);
    }
}

// Format exception into error details array
function entryzen_format_error($exception) {
    $error_types = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];
    
    $severity = 'Exception';
    if ($exception instanceof ErrorException) {
        $severity = $error_types[$exception->getSeverity()] ?? 'Unknown';
    }
    
    return [
        'timestamp' => date('Y-m-d H:i:s T'),
        'type' => get_class($exception),
        'severity' => $severity,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'php_version' => PHP_VERSION,
    ];
}

// Format fatal error into error details array
function entryzen_format_fatal_error($error) {
    $error_types = [
        E_ERROR => 'E_ERROR (Fatal)',
        E_PARSE => 'E_PARSE (Parse Error)',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_USER_ERROR => 'E_USER_ERROR',
    ];
    
    return [
        'timestamp' => date('Y-m-d H:i:s T'),
        'type' => 'Fatal Error',
        'severity' => $error_types[$error['type']] ?? 'Unknown',
        'message' => $error['message'],
        'file' => $error['file'],
        'line' => $error['line'],
        'trace' => 'N/A (Fatal errors do not have stack traces)',
        'url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'php_version' => PHP_VERSION,
    ];
}

// Display the error page
function entryzen_display_error_page($error_details) {
    // Build the copyable error text
    $error_text = "=== EntryZen Error Report ===\n";
    $error_text .= "Timestamp: " . $error_details['timestamp'] . "\n";
    $error_text .= "URL: " . $error_details['url'] . "\n";
    $error_text .= "Method: " . $error_details['method'] . "\n";
    $error_text .= "PHP Version: " . $error_details['php_version'] . "\n";
    $error_text .= "\n--- Error Details ---\n";
    $error_text .= "Type: " . $error_details['type'] . "\n";
    $error_text .= "Severity: " . $error_details['severity'] . "\n";
    $error_text .= "Message: " . $error_details['message'] . "\n";
    $error_text .= "File: " . $error_details['file'] . "\n";
    $error_text .= "Line: " . $error_details['line'] . "\n";
    $error_text .= "\n--- Stack Trace ---\n";
    $error_text .= $error_details['trace'] . "\n";
    $error_text .= "=== End of Error Report ===";
    
    // Set appropriate HTTP status code
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - EntryZen</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        .error-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .error-header {
            background: #dc3545;
            color: white;
            padding: 20px 30px;
        }
        .error-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .error-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .error-body {
            padding: 30px;
        }
        .error-message {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .error-message strong {
            color: #856404;
        }
        .instructions {
            background: #e7f3ff;
            border: 1px solid #b6d4fe;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .instructions h3 {
            color: #0c5460;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .instructions ol {
            margin-left: 20px;
            color: #0c5460;
        }
        .instructions li {
            margin-bottom: 5px;
        }
        .error-details-container {
            margin-top: 20px;
        }
        .error-details-container label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #555;
        }
        .error-textarea {
            width: 100%;
            height: 300px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
            resize: vertical;
            white-space: pre;
            overflow-wrap: normal;
            overflow-x: auto;
        }
        .copy-button {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .copy-button:hover {
            background: #218838;
        }
        .copy-button.copied {
            background: #6c757d;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .error-meta {
            font-size: 12px;
            color: #666;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <h1>⚠️ Something Went Wrong</h1>
            <p>An unexpected error has occurred</p>
        </div>
        <div class="error-body">
            <div class="error-message">
                <strong>Error:</strong> <?= htmlspecialchars($error_details['message']) ?>
            </div>
            
            <div class="instructions">
                <h3>📋 How to Report This Error</h3>
                <ol>
                    <li>Click the <strong>"Copy Error Report"</strong> button below</li>
                    <li>Send the copied text to the developer via email or message</li>
                    <li>Include a brief description of what you were doing when the error occurred</li>
                </ol>
            </div>
            
            <div class="error-details-container">
                <label for="error-text">Error Report (click to select all):</label>
                <textarea id="error-text" class="error-textarea" readonly onclick="this.select()"><?= htmlspecialchars($error_text) ?></textarea>
                <button class="copy-button" onclick="copyErrorReport()">📋 Copy Error Report</button>
            </div>
            
            <a href="javascript:history.back()" class="back-link">← Go Back</a>
            
            <div class="error-meta">
                Error occurred at <?= htmlspecialchars($error_details['timestamp']) ?>
            </div>
        </div>
    </div>
    
    <script>
        function copyErrorReport() {
            var textarea = document.getElementById('error-text');
            textarea.select();
            textarea.setSelectionRange(0, 99999); // For mobile
            
            try {
                document.execCommand('copy');
                var button = document.querySelector('.copy-button');
                var originalText = button.innerHTML;
                button.innerHTML = '✓ Copied!';
                button.classList.add('copied');
                
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.classList.remove('copied');
                }, 2000);
            } catch (err) {
                alert('Failed to copy. Please select the text manually and copy it.');
            }
        }
    </script>
</body>
</html>
    <?php
}

// Register the error handlers
set_error_handler('entryzen_error_handler');
set_exception_handler('entryzen_exception_handler');
register_shutdown_function('entryzen_shutdown_handler');

// Start output buffering to allow error page to replace any partial output
ob_start();


