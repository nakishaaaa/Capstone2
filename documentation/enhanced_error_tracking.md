# Enhanced Error Tracking System

## Overview
The Enhanced Error Tracking System provides comprehensive monitoring and notification capabilities for the Capstone2 printing service management system. It captures client-side errors, server-side failures, and data transfer issues to ensure system reliability and quick issue resolution.

## Components

### 1. Client-Side Error Tracking

#### JavaScript Error Tracker (`js/core/error-tracker.js`)
- **Global Error Handler**: Captures JavaScript errors, unhandled promise rejections, and resource loading failures
- **Network Monitoring**: Tracks connectivity issues and offline/online status
- **Queue Management**: Stores errors when offline and sends when connection is restored
- **Manual Logging**: Provides methods for custom error reporting

**Features:**
- Automatic error capture for all JavaScript errors
- Promise rejection handling
- Resource loading error detection
- Network connectivity monitoring
- Offline error queuing with automatic retry
- User ID tracking for better debugging

#### Client Error Logging API (`api/log_client_error.php`)
- Receives client-side errors via AJAX
- Stores errors in audit_logs table
- Provides detailed error context including stack traces
- IP address and user agent tracking

### 2. Server-Side Enhanced Audit Logging

#### Enhanced Audit Helper (`includes/enhanced_audit.php`)
Provides specialized logging methods for different types of operations:

- **File Operations**: Upload failures, permission issues, directory creation errors
- **Database Operations**: Query failures, unexpected row counts, transaction issues
- **API Failures**: External API call failures, timeout issues
- **Payment Processing**: PayMongo errors, transaction failures
- **Email Delivery**: SMTP failures, recipient issues
- **Session Management**: Session timeout, security issues
- **Form Validation**: Input validation failures
- **Business Logic**: Process flow errors, data inconsistencies
- **Performance Issues**: Slow operations, resource usage warnings
- **Security Concerns**: Suspicious activities, access violations

#### Global Helper Functions
```php
logFileError($operation, $fileName, $errorDetails, $userId = null)
logDbWarning($operation, $table, $affectedRows, $expectedRows = null, $userId = null)
logApiError($endpoint, $method, $responseCode, $errorMessage, $userId = null)
logPaymentError($paymentId, $amount, $status, $errorDetails, $userId = null)
logEmailError($recipient, $subject, $errorMessage, $userId = null)
monitorFunction($functionName, $callback, $userId = null)
```

### 3. Super Admin Notifications Enhancement

#### Comprehensive Error Categories
The super admin notifications now capture and categorize:

**Client-Side Errors:**
- JavaScript errors and exceptions
- Unhandled promise rejections
- Resource loading failures
- Network connectivity issues

**Server-Side Errors:**
- API failures and timeouts
- File operation errors
- Database warnings and failures
- Payment processing issues
- Email delivery failures
- Performance warnings
- Business logic errors
- Validation failures
- Session management issues

**Enhanced Notification Titles:**
- "JavaScript Error" - Client-side script failures
- "API Failure" - External service issues
- "File Operation Error" - Upload/download problems
- "Database Warning" - Query issues
- "Payment Processing Error" - Transaction failures
- "Email Delivery Failure" - SMTP issues
- "Performance Issue" - Slow operations
- "Business Logic Error" - Process flow problems
- "Validation Error" - Input validation failures
- "Resource Loading Error" - Asset loading failures
- "Network Connectivity Issue" - Connection problems
- "Unhandled Promise Error" - Async operation failures
- "Session Management Issue" - Authentication problems

## Implementation Details

### Error Types and Actions
The system uses specific action patterns in the audit_logs table:

- `javascript_error` - Client-side JavaScript errors
- `promise_rejection` - Unhandled promise rejections
- `resource_error` - Failed resource loading
- `network_error` - Network connectivity issues
- `api_failure` - External API call failures
- `file_*_error` - File operation errors (upload_error, delete_error, etc.)
- `db_*_warning` - Database operation warnings
- `payment_error` - Payment processing failures
- `email_failure` - Email delivery failures
- `performance_warning` - Performance issues
- `business_logic_error` - Business process errors
- `validation_failure` - Input validation errors
- `session_*` - Session management issues

### Database Schema
The system uses the existing `audit_logs` table with enhanced categorization:

```sql
SELECT 
    al.id,
    CASE 
        WHEN al.action LIKE '%javascript_error%' THEN 'JavaScript Error'
        WHEN al.action LIKE '%api_failure%' THEN 'API Failure'
        WHEN al.action LIKE '%file_%_error%' THEN 'File Operation Error'
        -- ... more categories
    END as title,
    CONCAT('Action: ', al.action, ' - ', al.description) as message,
    -- Severity classification
    CASE 
        WHEN al.action LIKE '%javascript_error%' THEN 'warning'
        WHEN al.action LIKE '%payment_error%' THEN 'error'
        WHEN al.action LIKE '%performance_warning%' THEN 'info'
        ELSE 'error'
    END as type
FROM audit_logs al
WHERE [comprehensive error pattern matching]
```

### Integration Points

#### Pages with Error Tracking
- Super Admin Dashboard (`super_admin_dashboard.php`)
- Admin Dashboard (`admin_page.php`)
- User Dashboard (`user_page.php`)

#### Enhanced APIs
- File Upload (`api/upload_image.php`)
- Email Notifications (`includes/email_notifications.php`)

#### Error Logging Functions
All critical operations now include error logging:
```php
// File operations
if (!move_uploaded_file($tmpName, $destination)) {
    logFileError('upload', $fileName, "Move failed from $tmpName to $destination");
}

// Email operations
try {
    $mail->send();
} catch (Exception $e) {
    logEmailError($recipient, $subject, $e->getMessage());
}

// API calls
if (!$apiResponse['success']) {
    logApiError($endpoint, 'POST', $responseCode, $apiResponse['error']);
}
```

## Benefits

### For Developers
- **Comprehensive Error Visibility**: All errors are captured and categorized
- **Real-time Notifications**: Immediate alerts for critical issues
- **Detailed Context**: Stack traces, user information, and system state
- **Performance Monitoring**: Slow operation detection and alerting
- **Client-side Debugging**: JavaScript errors with full context

### For System Administrators
- **Proactive Issue Detection**: Problems identified before user reports
- **Categorized Notifications**: Easy filtering and prioritization
- **Historical Tracking**: Complete audit trail of system issues
- **Performance Insights**: System bottleneck identification
- **Security Monitoring**: Suspicious activity detection

### For Business Operations
- **Reduced Downtime**: Faster issue identification and resolution
- **Improved User Experience**: Fewer unnoticed errors affecting customers
- **Data Integrity**: Better tracking of data transfer and processing issues
- **Compliance**: Complete audit trail for business processes

## Usage Examples

### Manual Error Logging
```javascript
// Client-side manual error logging
window.errorTracker.logManualError('Custom error message', {
    context: 'user_action',
    additional_data: 'relevant_info'
});
```

```php
// Server-side manual error logging
logFileError('custom_operation', 'filename.txt', 'Custom error description');
logApiError('/api/endpoint', 'POST', 500, 'Custom API error');
```

### Function Monitoring
```php
// Monitor function execution and log performance issues
$result = monitorFunction('processOrder', function() use ($orderData) {
    return processOrderLogic($orderData);
}, $userId);
```

### API Call Monitoring
```javascript
// Monitor API calls for errors
window.errorTracker.monitorApiCall(
    fetch('/api/endpoint', options),
    { endpoint: '/api/endpoint', method: 'POST' }
);
```

## Configuration

### Error Tracking Settings
- **Client Error Queue Size**: 50 errors maximum
- **Retry Interval**: Immediate when online, queued when offline
- **Performance Threshold**: 5 seconds for slow operation warnings
- **Notification Retention**: 24 hours for system errors, 7 days for support tickets

### Notification Categories
- **Security**: Failed logins, suspicious activity
- **System**: All enhanced error types
- **Customer Support**: Ticket and message notifications

## Maintenance

### Regular Tasks
1. **Monitor Error Trends**: Review error patterns and frequencies
2. **Performance Optimization**: Address recurring performance warnings
3. **Clean Up Logs**: Archive old audit logs to maintain performance
4. **Update Error Patterns**: Add new error types as system evolves

### Troubleshooting
- **Missing Notifications**: Check audit_logs table for error entries
- **Client Errors Not Logging**: Verify error-tracker.js is loaded
- **Performance Issues**: Review slow operation logs and optimize bottlenecks

## Future Enhancements

### Planned Features
- **Error Aggregation**: Group similar errors to reduce notification spam
- **Automatic Resolution**: Self-healing for common issues
- **Trend Analysis**: Pattern recognition for proactive maintenance
- **Integration Monitoring**: Third-party service health checks
- **Mobile App Support**: Error tracking for mobile applications

This enhanced error tracking system provides enterprise-level monitoring capabilities while maintaining system performance and user experience.
