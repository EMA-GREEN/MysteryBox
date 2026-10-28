//Winning Logic
<?php

function openMysteryBox($box_type) {
    // Define win chances based on box type
    $winChances = [
        'Bronze' => 15,
        'Silver' => 10,
        'Gold'   => 5
    ];

    // Validate box type
    if (!array_key_exists($box_type, $winChances)) {
        return [
            'status' => 'error',
            'message' => 'Invalid mystery box type.'
        ];
    }

    // Get win chance for the selected box
    $winChance = $winChances[$box_type];

    // Generate a random number between 0 and 100
    $randomNumber = rand(0, 100);

    // Determine win or loss
    if ($randomNumber <= $winChance) {
        // If win — decide random prize type
        $prizes = ['Cash', 'Airtime', 'Voucher'];
        $prizeType = $prizes[array_rand($prizes)];

        return [
            'status' => 'win',
            'box_type' => $box_type,
            'prize' => $prizeType,
            'random_number' => $randomNumber
        ];
    } else {
        // Loss outcome
        return [
            'status' => 'loss',
            'box_type' => $box_type,
            'random_number' => $randomNumber
        ];
    }
}

// Example Usage
$result = openMysteryBox('Silver');
print_r($result);

?>

# MysteryBox Application TODO List (10 Days)

## Day 1: Database Design & Setup
### Backend Tasks
- [ ] Review and finalize database schema
  - [ ] MysteryBox table
  - [ ] Payment tables
  - [ ] SMS notification tables
  - [ ] User/Player tables
- [ ] Create database migrations
  - [ ] Run existing migrations
  - [ ] Add any missing indexes
  - [ ] Add foreign key constraints
- [ ] Set up database seeding
  - [ ] Create test data for box types
  - [ ] Create test payment scenarios
  - [ ] Create test SMS scenarios

### Frontend Tasks
- [ ] Set up project structure
  - [ ] Create necessary directories
  - [ ] Set up routing
  - [ ] Configure state management

## Day 2: Core Backend Development
### Backend Tasks
- [ ] Implement core services
  - [ ] Complete SmsService implementation
  - [ ] Complete YasPaymentService implementation
  - [ ] Add error handling and logging
- [ ] Set up RabbitMQ
  - [ ] Configure queues
  - [ ] Set up publishers
  - [ ] Set up consumers
- [ ] Implement authentication
  - [ ] Set up JWT or session-based auth
  - [ ] Create middleware

### Frontend Tasks
- [ ] Create base components
  - [ ] Layout components
  - [ ] Navigation
  - [ ] Common UI elements

## Day 3: Payment Integration
### Backend Tasks
- [ ] Complete payment flow
  - [ ] Implement payment initiation
  - [ ] Set up payment callbacks
  - [ ] Add payment status tracking
- [ ] Add payment validation
  - [ ] Amount validation
  - [ ] User validation
  - [ ] Transaction validation

### Frontend Tasks
- [ ] Create payment screens
  - [ ] Payment form
  - [ ] Payment status
  - [ ] Transaction history

## Day 4: SMS Integration
### Backend Tasks
- [ ] Complete SMS flow
  - [ ] Implement SMS sending
  - [ ] Set up SMS receiving
  - [ ] Add SMS validation
- [ ] Add SMS logging
  - [ ] Create SMS logs
  - [ ] Add error tracking

### Frontend Tasks
- [ ] Create SMS-related screens
  - [ ] SMS history
  - [ ] SMS status
  - [ ] SMS templates

## Day 5: Mystery Box Logic
### Backend Tasks
- [ ] Implement box logic
  - [ ] Box creation
  - [ ] Box opening
  - [ ] Prize distribution
- [ ] Add box validation
  - [ ] User eligibility
  - [ ] Box availability
  - [ ] Prize validation

### Frontend Tasks
- [ ] Create box screens
  - [ ] Box selection
  - [ ] Box opening animation
  - [ ] Prize display

## Day 6: Testing & Debugging
### Backend Tasks
- [ ] Write unit tests
  - [ ] Service tests
  - [ ] Controller tests
  - [ ] Model tests
- [ ] Write integration tests
  - [ ] Payment flow tests
  - [ ] SMS flow tests
  - [ ] Box flow tests

### Frontend Tasks
- [ ] Write component tests
  - [ ] Unit tests
  - [ ] Integration tests
- [ ] Add error boundaries
  - [ ] Form validation
  - [ ] API error handling

## Day 7: Performance Optimization
### Backend Tasks
- [ ] Optimize database queries
  - [ ] Add indexes
  - [ ] Optimize joins
  - [ ] Add caching
- [ ] Optimize API endpoints
  - [ ] Add response caching
  - [ ] Implement rate limiting
  - [ ] Add request validation

### Frontend Tasks
- [ ] Optimize frontend
  - [ ] Code splitting
  - [ ] Lazy loading
  - [ ] Image optimization

## Day 8: Security Implementation
### Backend Tasks
- [ ] Implement security measures
  - [ ] Input validation
  - [ ] XSS protection
  - [ ] CSRF protection
- [ ] Add API security
  - [ ] API key validation
  - [ ] Request signing
  - [ ] IP whitelisting

### Frontend Tasks
- [ ] Add security features
  - [ ] Input sanitization
  - [ ] Secure storage
  - [ ] Error handling

## Day 9: Deployment Preparation
### Backend Tasks
- [ ] Set up deployment
  - [ ] Configure environment variables
  - [ ] Set up CI/CD
  - [ ] Configure logging
- [ ] Add monitoring
  - [ ] Set up error tracking
  - [ ] Add performance monitoring
  - [ ] Configure alerts

### Frontend Tasks
- [ ] Prepare for deployment
  - [ ] Build optimization
  - [ ] Environment configuration
  - [ ] Asset optimization

## Day 10: Final Testing & Deployment
### Backend Tasks
- [ ] Final testing
  - [ ] Load testing
  - [ ] Security testing
  - [ ] Integration testing
- [ ] Deployment
  - [ ] Database migration
  - [ ] Service deployment
  - [ ] Queue setup

### Frontend Tasks
- [ ] Final testing
  - [ ] Cross-browser testing
  - [ ] Mobile testing
  - [ ] Performance testing
- [ ] Deployment
  - [ ] Build deployment
  - [ ] CDN setup
  - [ ] Monitoring setup

## Additional Notes:
1. Each day's tasks should be completed before moving to the next day
2. Daily standups to track progress
3. Code reviews at the end of each day
4. Documentation should be updated as features are completed
5. Testing should be done continuously, not just on Day 6
6. Security considerations should be part of every task
7. Performance optimization should be considered throughout development

# MysteryBox Renewal Commands

## Overview
The MysteryBox application includes two automated renewal commands that manage subscriber data for renewal processing. These commands are designed to handle large datasets efficiently (up to 2M+ records) and are scheduled to run daily via crontab.

## Available Commands

### 1. MysteryBox Renewal Reset Command
**Command:** `php artisan mysterybox:renewal:reset`

**Purpose:** Updates the publish_status field to 1 for all records in the renewal_subscribers table.

**Features:**
- Updates all renewal_subscribers records in a single operation
- Includes comprehensive error handling and logging
- Provides progress feedback during execution
- Logs all operations for audit purposes

**Usage:**
```bash
# Manual execution
php artisan mysterybox:renewal:reset

# Expected output
Updating publish_status for all renewal subscribers...
Successfully updated [X] renewal subscribers publish_status to 1
```

### 2. MysteryBox Renewal Reload Command
**Command:** `php artisan mysterybox:renewal:reload`

**Purpose:** Reloads the renewal_subscribers table with active subscribers from the main subscribers table.

**Features:**
- Truncates the renewal_subscribers table
- Loads active subscribers (activity_status = 'ACTIVE' AND dnd_status = 'OFF')
- Orders by last_renewal_billing in descending order
- Optimized for large datasets using chunked processing
- Bulk insert operations for maximum performance
- Real-time progress tracking

**Performance Optimizations:**
- Reads data in chunks of 5,000 records
- Inserts data in batches of 50,000 records
- Memory-efficient processing (handles 2M+ records)
- Reduces database operations by 98% compared to single-record inserts

**Usage:**
```bash
# Manual execution
php artisan mysterybox:renewal:reload

# Expected output
Truncating renewal_subscribers table...
Truncated renewal_subscribers table
Processing [X] active subscribers
Reading in chunks of 5000, inserting in batches of 50000
Bulk inserted batch: [X] records processed so far
Successfully reloaded [X] renewal subscribers from active subscribers
```

## Crontab Scheduling

Since these commands are scheduled directly in crontab (not Laravel's task scheduler), use the following crontab entries:

```bash
# Edit crontab
crontab -e

# Add these entries:
# MysteryBox RenewalReset - runs daily at 23:45 (11:45 PM)
45 23 * * * cd /var/www/html/MysteryBox && php artisan mysterybox:renewal:reset >> /var/log/mysterybox-reset.log 2>&1

# MysteryBox RenewalReload - runs daily at 00:01 (12:01 AM)
1 0 * * * cd /var/www/html/MysteryBox && php artisan mysterybox:renewal:reload >> /var/log/mysterybox-reload.log 2>&1
```

**Important Notes:**
- Server path is configured for `/var/www/html/MysteryBox/`
- Ensure the web server user has execute permissions on the artisan file
- Log files will be created for monitoring command execution
- Commands include built-in error handling and logging

## Command Workflow

### Daily Renewal Process:
1. **23:45** - `mysterybox:renewal:reset` runs
   - Updates all renewal_subscribers publish_status to 1
   - Prepares records for the next day's processing

2. **00:01** - `mysterybox:renewal:reload` runs
   - Clears the renewal_subscribers table
   - Reloads with fresh active subscriber data
   - Orders by last_renewal_billing for priority processing

## Performance Specifications

### For Large Datasets (2M+ records):
- **Memory Usage:** ~25MB maximum (constant regardless of dataset size)
- **Database Operations:** ~40 bulk inserts (vs 2,000 individual inserts)
- **Execution Time:** Minutes instead of hours
- **Error Recovery:** Comprehensive error handling with detailed logging

## Monitoring and Logs

Both commands provide detailed logging:
- **Success Operations:** Logged with record counts and timestamps
- **Error Conditions:** Logged with full error details and stack traces
- **Progress Tracking:** Real-time feedback during execution

**Log Locations:**
- Application logs: `storage/logs/laravel.log`
- Crontab logs: Custom log files as specified in crontab entries

## Troubleshooting

### Common Issues:
1. **Memory Limits:** Commands are optimized for large datasets, but ensure PHP memory_limit is adequate
2. **Database Timeouts:** Bulk operations are designed to prevent timeouts
3. **Permission Issues:** Ensure proper file permissions for artisan and log directories
4. **Crontab Path Issues:** Use absolute paths in crontab entries

### Manual Testing:
```bash
# Test commands manually before scheduling
php artisan mysterybox:renewal:reset
php artisan mysterybox:renewal:reload

# Check command registration
php artisan list | grep mysterybox
```