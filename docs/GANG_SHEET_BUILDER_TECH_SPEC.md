# Gang Sheet Builder Technical Specification

## Purpose

This document defines the initial architecture for a new customer-facing DTF gang sheet builder for the Barebones Apparel WordPress / WooCommerce site.

This feature must remain separate from the existing internal quote tool.

The design goal is to let us build the stable parts now and defer business-rule details like final sheet sizes, pricing, art acceptance rules, and review policies until the questionnaire is returned.

## Current Assumptions

- Platform: WordPress + WooCommerce
- Hosting: Hostinger
- Payment processing: not finalized yet
- Ecommerce integration target: WooCommerce first, payment gateway specifics later
- Builder scope: customer-facing gang sheet builder for DTF sheets
- Pricing model for launch: fixed price by selected sheet size
- Order flow target: customer builds sheet, submits for review, then order/payment can be tied into WooCommerce

## Recommended Implementation Strategy

Build this as a standalone plugin:

`bw-gang-sheet-builder`

Do not extend or modify the existing `bw-project-quote-tool`.

The plugin should support two modes of operation:

1. Submission-first mode
   Customer builds a gang sheet and submits it for staff review before payment.

2. WooCommerce-linked mode
   After approval, the submission is converted into a WooCommerce cart/order flow using a hidden or programmatically-created product line item.

The first implementation should focus on submission-first mode while laying the foundation for WooCommerce order linkage.

## MVP Features

### Public Builder

- Customer-facing page via shortcode
- Sheet size selector
- Artwork upload
- Canvas/layout area
- Drag and resize artwork
- Optional rotation support
- Live price display based on selected sheet size
- Customer contact form
- Notes field
- Submit action

### Submission Storage

- Save customer info
- Save selected sheet size
- Save pricing snapshot at submission time
- Save artwork file references
- Save artwork placement data
- Save notes
- Save preview image if available later
- Save status and timestamps

### Admin Review

- Admin menu under WooCommerce
- List of submissions
- Submission detail screen
- Status updates
- Internal notes
- Placeholder support for approval/revision actions

### WooCommerce Foundation

- Submission records should be able to link to WooCommerce order IDs later
- Pricing snapshot should be stored independently from WooCommerce product catalog pricing
- Builder should be compatible with delayed payment flow

## Plugin Structure

Recommended directory structure:

```text
wordpress/public_html/wp-content/plugins/bw-gang-sheet-builder/
  bw-gang-sheet-builder.php
  includes/
    class-bw-gsb-plugin.php
  assets/
    css/
      bw-gsb.css
    js/
      bw-gsb.js
```

This keeps the first version simple while leaving room to split classes later.

## Data Model

The plugin should use custom database tables rather than only storing large structured payloads in post meta.

Reason:

- placements are structured data
- multiple uploaded assets belong to one submission
- statuses and later WooCommerce linkage need queryable fields
- admin review queue should be fast and easy to filter

### Table 1: submissions

Suggested table name:

`{wp_prefix}bw_gsb_submissions`

Suggested columns:

- `id`
- `created_at`
- `updated_at`
- `status`
- `customer_name`
- `customer_email`
- `customer_phone`
- `company_name`
- `sheet_code`
- `sheet_width`
- `sheet_height`
- `price`
- `currency`
- `notes`
- `admin_notes`
- `layout_json`
- `preview_attachment_id`
- `woocommerce_product_id`
- `woocommerce_order_id`
- `session_key`

### Table 2: assets

Suggested table name:

`{wp_prefix}bw_gsb_assets`

Suggested columns:

- `id`
- `submission_id`
- `attachment_id`
- `original_filename`
- `mime_type`
- `width_px`
- `height_px`
- `filesize_bytes`
- `created_at`

This lets us keep uploaded art normalized instead of burying everything in one JSON blob.

### layout_json structure

This should hold the sheet layout snapshot at submission time.

Suggested shape:

```json
{
  "sheet": {
    "code": "22x48",
    "width": 22,
    "height": 48,
    "price": 64.00
  },
  "items": [
    {
      "asset_id": 15,
      "x": 1.25,
      "y": 2.50,
      "width": 4.00,
      "height": 5.25,
      "rotation": 0,
      "z_index": 1
    }
  ],
  "builder_version": "0.1.0"
}
```

All measurements should be stored in sheet inches, not only pixels, so production logic stays stable even if the frontend canvas scale changes later.

## Configuration Model

Business rules should be configurable in code first, then movable to admin settings later.

For the first build, use filterable defaults for:

- sheet sizes
- sheet prices
- max upload size
- allowed MIME types
- spacing rules
- edge margin
- allowed transforms
- statuses

Recommended approach:

- create a `get_default_settings()` method
- allow overrides through WordPress filters
- later add admin settings UI without needing to redesign core logic

## WooCommerce Integration Strategy

We do not need final payment gateway work yet, but we should build the plugin with WooCommerce linkage in mind.

### Near-term

- Save submission independently first
- Admin reviews and approves
- Store placeholder `woocommerce_product_id` and `woocommerce_order_id`

### Next step after approval flow is confirmed

Two viable integration options:

1. Single hidden WooCommerce product
   Use one hidden product like "Custom Gang Sheet" and set cart item meta from the approved submission.

2. Programmatic per-size mapping
   Map each gang sheet size to a WooCommerce product ID.

Recommended path:

- Start with one hidden WooCommerce product
- Attach submission ID, sheet size, and approved price as cart item data
- Create order only after approval

This is the least brittle while payment processing is still undecided.

## Frontend Architecture

The builder frontend should avoid a heavy framework in v1 unless needed.

Recommended v1:

- WordPress shortcode renders container + config JSON
- Vanilla JS builder logic in one asset file
- HTML5 positioned layout area
- Save placement data in hidden field or AJAX endpoint

### Builder state

The browser state should track:

- selected sheet
- uploaded assets
- active item
- x/y position
- width/height
- rotation
- current price
- customer form values

### UX notes

Keep the initial builder simple:

- fixed sheet aspect preview
- upload art
- click to add art to sheet
- drag to move
- drag handle to resize
- optional rotate control
- clear summary panel

Do not block v1 on:

- auto nesting
- background removal
- vector parsing
- advanced snapping
- multi-sheet orders

## Admin Experience

Add submenu pages under WooCommerce:

- Gang Sheet Submissions
- Gang Sheet Settings

### Submission list should show

- submission ID
- created date
- customer name
- email
- sheet size
- price
- status
- WooCommerce order link if present

### Submission detail should show

- customer details
- selected sheet details
- uploaded assets
- layout preview placeholder
- raw placement summary
- notes
- internal notes
- status controls
- WooCommerce link fields

## Status Model

Default statuses should be:

- submitted
- reviewing
- needs_changes
- approved
- rejected
- in_production
- completed

These should be filterable later if operations wants different labels.

## Security Requirements

- Use WordPress nonces for form submissions
- Restrict admin pages to WooCommerce-capable staff
- Sanitize all customer input
- Validate uploaded MIME types and attachment ownership
- Escape all output in admin and frontend views

## Hostinger / Infrastructure Notes

Because hosting is on Hostinger:

- avoid requiring Node build tooling for the first version
- keep frontend assets plain CSS/JS
- do not assume background workers or long-running jobs
- keep file handling inside standard WordPress media APIs

## Build Order

### Phase 1: foundation

- plugin bootstrap
- DB tables
- shortcode
- public builder shell
- upload handling
- admin review list

### Phase 2: builder behavior

- drag/resize interactions
- layout serialization
- submission save flow
- status editing

### Phase 3: WooCommerce linkage

- approved submission to cart/order bridge
- order meta display
- customer follow-up flow

### Phase 4: production rules

- apply questionnaire answers
- validation warnings
- file quality checks
- spacing and safe-zone enforcement

## What Can Be Built Before Questionnaire Answers

Safe to build now:

- plugin structure
- database schema
- admin pages
- public shortcode shell
- upload pipeline
- builder state model
- placeholder sheet configuration
- status system
- WooCommerce linkage placeholders

Should remain configurable until answers come back:

- exact sizes
- exact pricing
- accepted file types
- spacing rules
- edge margins
- warning/rejection logic
- payment timing

## Immediate Next Development Step

Create the plugin scaffold and implement:

- activation hook
- table creation
- shortcode
- admin menu
- placeholder public builder page
- placeholder admin submissions list

That gets the project structurally ready while waiting for the business-rule answers.
