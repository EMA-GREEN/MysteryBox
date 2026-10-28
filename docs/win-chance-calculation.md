# Win Chance Calculation Documentation

## Overview

The Mystery Box system uses a **percentage-based win chance** mechanism to determine if a user wins additional rewards beyond guaranteed rewards when opening a box.

## How Win Chance Works

### 1. Configuration

Each box type in `config/boxes.php` has a `win_chance` value (0-100) that represents the **percentage probability** of winning additional rewards.

**Example:**
```php
'1' => [
    'name' => 'Bronze',
    'amount' => 300,
    'win_chance' => 2,  // 2% chance to win
    'rewards' => [
        'guaranteed' => [...],  // Always given
        'possible' => [...]      // Only if win_chance check passes
    ]
]
```

### 2. Calculation Process

When a box is opened, the system follows these steps:

#### Step 1: Generate Random Number
```php
$randomNumber = mt_rand(1, 100);  // Random integer between 1 and 100
```

#### Step 2: Compare with Win Chance
```php
$isWin = $randomNumber <= $winChance;
```

**Logic:**
- If `randomNumber` is **less than or equal to** `winChance`, the user wins (`isWin = true`)
- If `randomNumber` is **greater than** `winChance`, the user loses (`isWin = false`)

#### Step 3: Determine Rewards

The system processes rewards in this order:

1. **Guaranteed Rewards** (Always given)
   - These are **always** awarded regardless of the win chance check
   - Examples: Loyalty points, jackpot entries
   - Processed **before** the win chance check

2. **Possible Rewards** (Only if win chance check passes)
   - Only awarded if `isWin = true`
   - Only if the `possible` section has `is_enabled => true`
   - Only if individual rewards have `is_enabled => true` (or not set)
   - One random reward is selected from the enabled possible rewards

#### Step 4: Final Status Determination

```php
$result['status'] = !empty($rewards) ? 'win' : 'loss';
```

**Important:** Even if the win chance check fails, if there are guaranteed rewards, the status will be `'win'`.

## Examples

### Example 1: Bronze Box (win_chance = 2%)

**Configuration:**
```php
'win_chance' => 2,
'rewards' => [
    'guaranteed' => [
        ['type' => 'loyalty_points', 'min' => 5, 'max' => 20]
    ],
    'possible' => [
        'is_enabled' => true,
        'rewards' => [
            ['type' => 'cash', 'min' => 500, 'max' => 5000],
            ['type' => 'airtime', 'min' => 500, 'max' => 2000]
        ]
    ]
]
```

**Scenario A: User Wins (randomNumber = 1)**
1. Random number generated: `1`
2. Win check: `1 <= 2` → `true` ✅
3. Guaranteed rewards: 5-20 loyalty points (always given)
4. Possible rewards: Random selection from cash or airtime
5. Result: `status = 'win'` with both guaranteed and possible rewards

**Scenario B: User Loses (randomNumber = 50)**
1. Random number generated: `50`
2. Win check: `50 <= 2` → `false` ❌
3. Guaranteed rewards: 5-20 loyalty points (still given)
4. Possible rewards: None (win check failed)
5. Result: `status = 'win'` (because guaranteed rewards exist)

**Scenario C: User Loses (randomNumber = 100)**
1. Random number generated: `100`
2. Win check: `100 <= 2` → `false` ❌
3. Guaranteed rewards: 5-20 loyalty points (still given)
4. Possible rewards: None
5. Result: `status = 'win'` (because guaranteed rewards exist)

### Example 2: Gold Box (win_chance = 2%)

**Configuration:**
```php
'win_chance' => 2,
'rewards' => [
    'guaranteed' => [
        ['type' => 'loyalty_points', 'min' => 20, 'max' => 50],
        ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000]
    ],
    'possible' => [
        'is_enabled' => true,
        'rewards' => [
            ['type' => 'cash', 'min' => 5000, 'max' => 10000],
            ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch']]
        ]
    ]
]
```

**Scenario: User Wins (randomNumber = 2)**
1. Random number: `2`
2. Win check: `2 <= 2` → `true` ✅
3. Guaranteed rewards:
   - 20-50 loyalty points
   - Daily jackpot entry (100,000 Tsh)
4. Possible rewards: Random selection (cash OR tech gadget)
5. Result: `status = 'win'` with all rewards

## Win Chance Values

| Win Chance | Probability | Meaning |
|------------|-------------|---------|
| 0 | 0% | Never wins possible rewards (only guaranteed) |
| 1 | 1% | 1 in 100 chance |
| 2 | 2% | 1 in 50 chance |
| 5 | 5% | 1 in 20 chance |
| 10 | 10% | 1 in 10 chance |
| 25 | 25% | 1 in 4 chance |
| 50 | 50% | 1 in 2 chance |
| 100 | 100% | Always wins possible rewards |

## Important Notes

### 1. Guaranteed vs Possible Rewards

- **Guaranteed rewards** are **always** given, regardless of win chance
- **Possible rewards** are **only** given if:
  - Win chance check passes (`randomNumber <= winChance`)
  - `possible.is_enabled = true`
  - Individual reward has `is_enabled = true` (or not set)

### 2. Status Determination

The final `status` is determined by:
```php
$result['status'] = !empty($rewards) ? 'win' : 'loss';
```

This means:
- If there are **any rewards** (guaranteed or possible), status = `'win'`
- If there are **no rewards at all**, status = `'loss'`

### 3. Disabled Rewards

If `possible.is_enabled = false` or all individual possible rewards are disabled:
- The win chance check still runs
- But `isWin` is set to `false` if no enabled rewards exist
- Only guaranteed rewards are given

### 4. Random Number Generation

- Uses PHP's `mt_rand(1, 100)` function
- Generates a random integer between **1 and 100** (inclusive)
- Each box opening is independent (no memory of previous results)

## Code Location

The win chance calculation is implemented in:
- **File:** `app/Http/Controllers/MysteryBoxController.php`
- **Method:** `processBoxOpening($box_type)`
- **Lines:** 358-488

## Testing Win Chance

To test different win scenarios, you can temporarily modify the code:

```php
// For testing: Force a win
$randomNumber = 1;  // Always wins if win_chance >= 1

// For testing: Force a loss
$randomNumber = 100;  // Always loses if win_chance < 100
```

## Real-World Example

**Bronze Box (300 Tsh, win_chance = 2%)**

Out of 100 box openings:
- **~98 boxes** will only get guaranteed rewards (loyalty points)
- **~2 boxes** will get guaranteed rewards + possible rewards (cash/airtime)

This means:
- Every user gets loyalty points (guaranteed)
- Approximately 2% of users get additional cash or airtime rewards

## Summary

1. **Win Chance** = Percentage probability (0-100) defined in box config
2. **Random Number** = Generated between 1-100 for each box opening
3. **Win Check** = `randomNumber <= winChance`
4. **Guaranteed Rewards** = Always given
5. **Possible Rewards** = Only if win check passes AND enabled
6. **Final Status** = `'win'` if any rewards exist, `'loss'` if none

