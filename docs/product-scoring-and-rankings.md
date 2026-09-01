# Product Scoring And Rankings

## Purpose

This document explains how product safety rankings are produced in LabelWise.

It focuses on:

- what information is used to score a product
- what each scoring metric means
- how the final ranking is calculated
- why some products are intentionally capped below a perfect score

The goal of the scoring system is to give a practical, conservative view of product quality and safety based on the product information available at the time of scanning.

## What The System Scores

The platform currently calculates score-based rankings for two product groups:

- food products
- cosmetic products

Other product families may still be stored and returned by the system, but they are not forced into food or cosmetic scoring when the supporting data is not reliable enough.

## Where The Information Comes From

The score is not guessed from the barcode alone. It is built from product data collected and stored by the platform.

The main data sources are:

### External product sources

The system can pull product details from external product databases, including:

- Open Food Facts for food products
- Open Beauty Facts for cosmetic products

These sources provide product details such as:

- product name
- brand
- ingredient list
- nutrition values
- packaging details
- category tags

### Internal product records

Once a product is processed, its information is stored locally in the platform database. This includes:

- the main product record
- ingredient records
- product-to-ingredient links
- nutrition details
- the final calculated score and score breakdown

### Ingredient risk classifications

Ingredients are also classified internally by risk level, typically as:

- low risk
- medium risk
- high risk

These classifications are used heavily in both food and cosmetic scoring.

## High-Level Scoring Principles

Before going into formulas, it helps to understand the rules behind the design.

The ranking system is intentionally conservative. That means:

- a product does not receive a perfect score by default
- missing or incomplete data reduces confidence in the result
- packaging can affect the score, not just ingredients
- heavily processed products are penalized
- products with incomplete ingredient or nutrition data may be capped

This approach is meant to avoid presenting weak or incomplete product information as if it were highly trustworthy.

## Food Product Scoring

Food products are scored across five separate areas:

1. Nutrition
2. Ingredient quality
3. Additive risk
4. Processing level
5. Packaging impact

Each area produces its own score. Those five scores are then combined into one overall product score.

### Food scoring weights

The final food score uses these weights:

- Nutrition: 35%
- Ingredient quality: 25%
- Additive risk: 15%
- Processing level: 10%
- Packaging impact: 15%

In plain terms, nutrition matters the most, ingredient quality matters next, and processing and packaging are still important but carry a smaller share of the final result.

### 1. Nutrition score

The nutrition score starts from a strong baseline and is adjusted up or down based on the nutritional profile.

The system looks mainly at:

- sugar
- saturated fat
- total fat
- sodium
- fiber
- protein

#### Nutrition penalties

The score is reduced when the product contains too much of the following:

- sugar
- saturated fat
- total fat
- sodium

This means products with high sugar, high salt, or high unhealthy fat content are pulled downward.

#### Nutrition boosts

The score can improve when the product contains useful nutritional positives such as:

- meaningful fiber
- meaningful protein

This means foods with better nutritional balance can recover some points.

#### Special handling for water

Plain water is treated separately from ordinary food because its expected profile is different. The system gives water a strong nutrition score by default when the product is correctly identified as water.

### 2. Ingredient quality score

This score reflects the overall quality of the ingredient list.

Each ingredient is assigned a risk level:

- low-risk ingredients contribute strongly to the score
- medium-risk ingredients pull the score down
- high-risk ingredients pull the score down much more sharply

The ingredient quality score is based on the average quality of the ingredient list.

#### Ingredient count adjustment

The system also considers how long the ingredient list is:

- shorter ingredient lists can receive a small boost
- very long ingredient lists can receive a penalty

This reflects the idea that simpler formulations are often easier to trust than heavily engineered ones.

### 3. Additive risk score

This score focuses on the risk profile of additives and concerning ingredients found in the formulation.

It uses the same underlying ingredient risk levels, but looks at them from the perspective of additive burden and processing-related concern.

In simple terms:

- more low-risk ingredients support a better additive score
- more medium-risk and high-risk ingredients reduce the score

### 4. Processing score

The processing score estimates how processed the product appears to be.

The system uses one of two methods:

- a known NOVA processing classification, when available
- a fallback estimate based on the number of ingredients

#### NOVA-based scoring

When NOVA information is available, the product is scored according to its group:

- NOVA 1 scores highest
- NOVA 2 scores well
- NOVA 3 is more moderate
- NOVA 4 scores lowest

This reflects the usual understanding that ultra-processed foods should score lower than minimally processed foods.

#### Ingredient-count fallback

If NOVA data is not available, the platform falls back to a simpler rule:

- fewer ingredients usually means less processing
- more ingredients usually means more processing

This fallback is not meant to be perfect, but it gives the system a reasonable conservative estimate when better processing data is missing.

### 5. Packaging score

Packaging also affects the food score.

This is important because the system is not only evaluating what is inside the product, but also the product's packaging profile where data is available.

The system checks packaging material information and adjusts the score based on whether the product appears to use:

- plastic
- glass
- metal
- paper or cardboard

In general:

- plastic packaging lowers the score more noticeably
- glass performs better
- metal and paper are treated more moderately

#### Water and plastic packaging

Bottled water in plastic packaging is treated especially carefully.

Even if the water itself looks nutritionally clean, the product can still be capped below a perfect score because packaging impact is part of the rating logic.

## Food Score Safeguards And Caps

To prevent overly generous rankings, the platform applies several caps.

### Sparse food data cap

If verified nutrition data is missing and the ingredient data is too limited, the system caps the score at a conservative ceiling instead of allowing it to look excellent based on weak evidence.

### Plastic bottled water cap

Plastic bottled water is capped below a perfect score even when the water itself is otherwise strong.

### Perfect score cap

Food products are also capped below an absolute perfect score. This prevents the system from handing out a true "perfect" rating too easily.

## Cosmetic Product Scoring

Cosmetic products use a different scoring model from food products.

Instead of nutrition and processing, the cosmetic score focuses on risk themes commonly associated with personal care products.

The cosmetic score is built from four areas:

1. Irritant risk
2. Endocrine-disruption risk
3. Allergen risk
4. Environmental risk

The final cosmetic score is the average of these four category scores.

### 1. Irritant risk score

This score checks the ingredient list for terms commonly associated with irritation, such as certain fragrance-related or sensitizing ingredients.

The more such matches are found, the lower the irritant score becomes.

### 2. Endocrine-disruption risk score

This score checks for ingredients associated with hormone-disruption concerns.

If these ingredients appear in the list, the score drops more sharply.

### 3. Allergen risk score

This score focuses on common fragrance allergens and similar ingredients that may be problematic for sensitive users.

More allergen matches lead to a lower score.

### 4. Environmental risk score

This score considers ingredients associated with environmental concern, such as certain microplastic-related or pollutant-linked terms.

If these are detected, the environmental score is reduced.

## Cosmetic Score Safeguards

### Missing ingredient list cap

If the ingredient list is missing, the system does not pretend to know the product is safe.

Instead, it assigns a conservative score and caps the product below a high rating.

This is an intentional trust safeguard. A cosmetic product cannot be confidently rated without ingredient data.

### Perfect score cap

Cosmetic products are also prevented from reaching an unrestricted perfect score.

## How Product Rankings Are Presented

The platform stores the underlying numeric score, but it also converts that score into a simpler label that is easier to understand.

### Food grade labels

Food products are mapped into grade-style rankings:

- 90 and above: A+
- 80 to 89: A
- 70 to 79: B+
- 60 to 69: B
- 50 to 59: C
- 40 to 49: D
- below 40: F

This gives users a quick summary without hiding the numeric score behind it.

### Food score colors

Food products also use a color interpretation:

- 70 and above: green
- 50 to 69: yellow
- 30 to 49: orange
- below 30: red

### Cosmetic safety labels

Cosmetic products are translated into plain-language safety levels:

- 80 and above: Excellent
- 60 to 79: Good
- 40 to 59: Moderate
- 20 to 39: Poor
- below 20: Avoid

This helps non-technical users understand the result at a glance.

## What The Score Breakdown Contains

In addition to the overall result, the system stores a score breakdown so the result can be explained rather than appearing as a mystery number.

For food products, the breakdown includes:

- nutrition score
- ingredient score
- additive score
- processing score
- packaging score
- supporting confidence metadata
- supporting completeness metadata

For cosmetic products, the breakdown includes:

- irritant score
- endocrine score
- allergen score
- environmental score
- supporting confidence metadata
- supporting completeness metadata

The system can also store:

- warnings
- benefits
- a short explanation sentence

This allows the application to explain both the positives and the concerns behind a ranking.

## Examples Of Why A Score May Drop

Below are common situations that reduce a product's ranking.

### Food examples

- sugar is above the preferred threshold
- sodium is above the preferred threshold
- several medium-risk or high-risk ingredients are present
- the product appears highly processed
- the ingredient list is very long
- plastic packaging is detected
- the product data is incomplete

### Cosmetic examples

- fragrance-related irritants are present
- endocrine-disruption keywords are found
- known fragrance allergens appear in the ingredient list
- environmentally concerning ingredients are detected
- the ingredient list is missing or incomplete

## Why Missing Data Matters

One of the most important parts of this ranking system is how it treats incomplete information.

The system does not assume that missing data means a product is safe.

Instead:

- missing nutrition data weakens confidence in food scoring
- missing ingredient data weakens confidence in both food and cosmetic scoring
- missing packaging data prevents packaging from being assessed fully
- incomplete data may trigger conservative caps

This protects against inflated rankings based on weak evidence.

## How Ingredients Receive Risk Levels

Ingredient risk levels come from internal classification rules and stored ingredient records.

In practice, this means:

- some ingredients are already known and saved in the ingredient database with an assigned risk level
- new or unsaved ingredients can be classified automatically using keyword-based rules

For example, some ingredient names associated with artificial sweeteners, preservatives, colorants, parabens, phthalates, and fragrance markers may be treated as higher concern than ordinary base ingredients.

This ingredient classification step is important because it affects:

- ingredient quality scoring
- additive scoring
- cosmetic risk scoring

## What The Ranking Is Meant To Represent

The score is best understood as a practical safety-and-quality ranking based on the product information available to the system.

It is not:

- a medical diagnosis
- a regulatory approval status
- a guarantee that a product is universally safe for every individual

It is meant to help users compare products more clearly by applying the same rules consistently across scans.

## Summary

In simple terms:

- food products are scored on nutrition, ingredient quality, additive burden, processing, and packaging
- cosmetic products are scored on irritant, endocrine, allergen, and environmental risk
- incomplete data lowers trust and may cap the final result
- packaging can affect the score, especially for plastic bottled water
- the final ranking is always backed by a stored score breakdown, warnings, and explanation text

This makes the product ranking system explainable, conservative, and easier to review than a simple black-box score.
