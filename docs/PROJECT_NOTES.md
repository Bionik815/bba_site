# PROJECT_NOTES.md
## Barebones Apparel Multi-Client WooCommerce Platform

---

# Project Overview

This project is a **WordPress + WooCommerce multi-client storefront system** designed to support many branded client stores (schools, athletes, businesses, musicians, etc.) under one centralized ecommerce installation.

Instead of creating separate WooCommerce sites for every client, all storefronts operate inside a **single WooCommerce instance** with shared checkout, fulfillment, and product management.

Each client receives:

- A branded storefront page
- A filtered catalog of their products
- Placement in the global mega menu
- Optional subdomain landing pages
- Shared checkout and fulfillment through the main WooCommerce store

The system is designed to scale to **dozens or hundreds of client storefronts**.

---

# Core Technology Stack

### Platform
WordPress  
WooCommerce  
Elementor Pro  
Astra Theme

### Hosting
Hostinger (currently staging environment)

### Development Workflow
Local development repository → deploy to hosting environment

---

# High Level Architecture

The platform is built as a **multi-tenant WooCommerce architecture**.

Example structure:

```
barebones-apparel.com
```

Primary ecommerce domain where all products, checkout, and order processing occur.

Client storefronts exist as:

```
/creator/{client-name}/
```

Example:

```
/creator/axe-n-dagger/
```

Optional future configuration:

```
clientname.barebones-apparel.com
```

Subdomain landing pages redirect to the creator store page.

All purchases remain processed through the central WooCommerce installation.

---

# Custom Plugin System

Several custom plugins extend WordPress and WooCommerce to support the multi-client architecture.

---

# 1. Mega Menu System

Plugin:

```
bw-mega-menu-pro
```

Purpose:

Provides the **global mega menu** listing all client storefronts grouped by category.

---

## Custom Post Type

```
bw_creator
```

Represents a client storefront.

Each creator entry contains:

- Client name
- Store URL
- Logo (featured image)
- Group assignment
- Menu visibility
- Menu order

---

## Custom Taxonomy

```
bw_group
```

Groups creators into sections such as:

- Barebones Apparel
- Athletes
- Businesses
- Music
- Schools

These groups appear as sections in the mega menu.

---

## Mega Menu Features

- Full screen overlay menu
- Client logo display
- Client name under logo
- Horizontal scrolling logo rows
- Category group sections
- Sticky header integration
- Optional grayscale logo display

Menu rendered using shortcode:

```
[bw_mega_menu]
```

Placed in the Astra header.

---

# 2. Product Personalization Plugin

Custom plugin supporting **custom apparel personalization fields**.

Capabilities include:

- Optional personalization fields
- Character limits
- Dynamic price adjustments
- Live price updates on product page
- Additional charges based on selected options

Example personalization fields:

```
Player Name
Player Number
Year
```

Price updates dynamically on the product page instead of only updating at checkout.

---

# 3. Size Guide Manager

Plugin:

```
bw-size-guides
```

Purpose:

Provides centralized management of apparel sizing charts.

Admin workflow:

WooCommerce → Size Guides

Admin can:

- Add apparel company
- Upload size guide image
- Assign size guide to products
- Override image per product

Frontend behavior:

Adds a **Size Guide tab** to WooCommerce product pages displaying the associated size chart.

---

# 4. Commission Tracking Plugin

Custom plugin used for **client commission tracking**.

Features:

Admin-only product field:

```
Commission Amount
```

Supports:

- Default commission value per client
- Per-product commission overrides

Used for reporting and client payout exports.

---

# Product Organization

Products remain standard WooCommerce products but are associated with clients through categories or metadata.

Typical structure:

```
AVC Marauders Athletics
 ├ Spirit Wear
 ├ Performance Gear
 ├ Staff & Coaches
 └ Accessories
```

Products may include attributes such as:

```
Size
Color
Customization Options
```

---

# Client Store Pages

Client storefront pages currently exist at:

```
/creator/{client-name}/
```

Example:

```
/creator/axe-n-dagger/
```

These pages display:

- Client branding
- Product collections
- Category sections
- Featured products

Future development will convert these into reusable Elementor templates.

---

# Subdomain Strategy (Future)

Clients may optionally receive branded subdomain landing pages.

Example:

```
axendagger.barebones-apparel.com
```

These pages would:

- Display client branding
- Feature selected products
- Link to the full client store page

WooCommerce checkout remains centralized.

---

# Elementor Usage

Elementor Pro handles:

- Product templates
- Landing page design
- Client store layouts

Planned development:

Reusable **Creator storefront templates**.

Current limitation discovered:

Elementor Theme Builder does not yet expose the custom post type for creators in template conditions due to CPT configuration.

Temporary solution:

Client storefronts will initially be built as standard Elementor pages before converting to templates.

---

# Current Development Focus

The immediate development focus is building a **proof-of-concept client storefront** for:

```
AVC Marauders Athletics
```

Client information:

Type: Community College  
Student population: ~16,000  
Current status: No official online apparel store

Meeting goal:

Demonstrate a potential official athletics apparel storefront.

---

# Proof of Concept Page Structure

Page:

```
AVC Athletics Store (Demo)
```

Sections include:

### Hero Section

AVC Athletics branding  
Call-to-action button

---

### Shop by Category

Example categories:

- Spirit Wear
- Performance Gear
- Staff & Coaches
- Accessories

---

### Featured Products

WooCommerce product grid filtered by AVC category.

---

### Ordering Window Section

Explains the production model:

Orders collected during store window → items produced after store closes.

---

### Staff / Coaches Apparel

Dedicated section for staff gear.

---

# Mega Menu Status

Completed functionality:

- Creator logo display
- Grouped creator sections
- Horizontal scroll logo rows
- Full screen overlay menu
- Sticky header integration

Potential improvements:

- Mobile menu behavior
- Scroll controls

---

# WooCommerce Status

Currently functional:

- Variable products
- Attribute-based pricing
- Personalization fields
- Size guide integration
- Commission tracking

Planned features:

- Player pack bundle products
- Advanced category filtering
- Enhanced reporting

---

# Remaining Major Features

## Creator Store Templates

Convert current client storefront pages into reusable templates.

---

## Product Repository

Maintain a master catalog of apparel items that can be enabled or disabled per client.

---

## Reporting Enhancements

Order reporting grouped by:

- Client
- Product
- Commission values

---

## Subdomain Landing Pages

Client branded microsites linking to WooCommerce catalog.

---

# Development Objectives

Primary goal:

Build a scalable platform for managing **many branded apparel storefronts within a single WooCommerce environment**.

Key priorities:

- Efficient client onboarding
- Clear order identification per client
- Centralized fulfillment workflow
- Strong client branding
- Scalable store management

---

# Current Project Status

Working systems:

- Mega menu infrastructure
- Creator management
- Custom WooCommerce plugins
- Product customization fields
- Size guide management
- Commission tracking

In progress:

- Client storefront page templates
- Product category organization
- Frontend store design

Planned:

- Subdomain landing pages
- Product template repository
- Advanced order reporting

---

# Important Code Locations

Custom plugin directories:

```
/wp-content/plugins/bw-mega-menu-pro/
/wp-content/plugins/bw-size-guides/
/wp-content/plugins/bw-product-customization/
/wp-content/plugins/bw-creator-*
```

Key Custom Post Type:

```
bw_creator
```

Key taxonomy:

```
bw_group
```

WooCommerce remains the core ecommerce engine.

---

# Immediate Development Priority

Complete the **AVC Marauders Athletics demo storefront page**, then convert its layout into a reusable template for onboarding additional client stores.

---