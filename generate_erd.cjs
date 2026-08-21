/**
 * Chen ERD Generator for SabaPost System
 * Generates a complete Draw.io XML with:
 *  - Entities (rectangles)
 *  - ALL Attributes (ellipses, PK=underlined, FK=dashed)
 *  - Relationships (diamonds) with Arabic labels
 *  - Cardinality labels (1, N, M) on every edge
 */
const fs = require('fs');

// ═══════════════════════════════════════════════════════════════════════════
// ENTITY DEFINITIONS  (cx/cy = center of entity)
// ═══════════════════════════════════════════════════════════════════════════
const ENTITIES = [

  /* ── AUTH ─────────────────────────────────────────────────────────── */
  { id: 'roles', label: 'roles', cx: 400, cy: 400,
    fill: '#dae8fc', stroke: '#6c8ebf', attrDir: 'above',
    attrs: [
      { name: 'role_id', pk: true },
      { name: 'role_name' }
    ]
  },
  { id: 'users', label: 'users', cx: 1050, cy: 800,
    fill: '#dae8fc', stroke: '#6c8ebf', attrDir: 'above',
    attrs: [
      { name: 'user_id', pk: true },
      { name: 'full_name' },
      { name: 'email' },
      { name: 'phone' },
      { name: 'location' },
      { name: 'password_hash' },
      { name: 'account_status' },
      { name: 'created_at' },
      { name: 'deleted_at' }
    ]
  },
  { id: 'user_sessions', label: 'user_sessions', cx: 400, cy: 1200,
    fill: '#dae8fc', stroke: '#6c8ebf', attrDir: 'below',
    attrs: [
      { name: 'session_id', pk: true },
      { name: 'user_id', fk: true },
      { name: 'device_name' },
      { name: 'device_id' },
      { name: 'ip_address' },
      { name: 'fcm_token' },
      { name: 'last_active' },
      { name: 'is_revoked' }
    ]
  },

  /* ── LOCATION ─────────────────────────────────────────────────────── */
  { id: 'governorates', label: 'governorates', cx: 2000, cy: 300,
    fill: '#fff2cc', stroke: '#d6b656', attrDir: 'above',
    attrs: [
      { name: 'gov_id', pk: true },
      { name: 'name' }
    ]
  },
  { id: 'regions', label: 'regions', cx: 2000, cy: 800,
    fill: '#fff2cc', stroke: '#d6b656', attrDir: 'above',
    attrs: [
      { name: 'region_id', pk: true },
      { name: 'gov_id', fk: true },
      { name: 'name' }
    ]
  },
  { id: 'streets', label: 'streets', cx: 2000, cy: 1300,
    fill: '#fff2cc', stroke: '#d6b656', attrDir: 'below',
    attrs: [
      { name: 'street_id', pk: true },
      { name: 'region_id', fk: true },
      { name: 'name' }
    ]
  },

  /* ── SCREENS ──────────────────────────────────────────────────────── */
  { id: 'screen_types', label: 'screen_types', cx: 2900, cy: 250,
    fill: '#f8cecc', stroke: '#b85450', attrDir: 'above',
    attrs: [
      { name: 'type_id', pk: true },
      { name: 'type_name' },
      { name: 'resolution_width' },
      { name: 'resolution_height' },
      { name: 'orientation' }
    ]
  },
  { id: 'screens', label: 'screens', cx: 2900, cy: 900,
    fill: '#f8cecc', stroke: '#b85450', attrDir: 'above',
    attrs: [
      { name: 'screen_id', pk: true },
      { name: 'owner_id', fk: true },
      { name: 'type_id', fk: true },
      { name: 'street_id', fk: true },
      { name: 'linked_by', fk: true },
      { name: 'screen_name' },
      { name: 'status' },
      { name: 'mac_address' },
      { name: 'installation_date' }
    ]
  },
  { id: 'screen_pricing_slots', label: 'screen_pricing_slots', cx: 2900, cy: 1600,
    fill: '#f8cecc', stroke: '#b85450', attrDir: 'below',
    attrs: [
      { name: 'slot_id', pk: true },
      { name: 'screen_id', fk: true },
      { name: 'start_time' },
      { name: 'end_time' },
      { name: 'price_multiplier' }
    ]
  },
  { id: 'screen_commands', label: 'screen_commands', cx: 3700, cy: 1700,
    fill: '#f8cecc', stroke: '#b85450', attrDir: 'below',
    attrs: [
      { name: 'id', pk: true },
      { name: 'target_screen' },
      { name: 'command' },
      { name: 'payload' },
      { name: 'executed_at' }
    ]
  },

  /* ── ADS ──────────────────────────────────────────────────────────── */
  { id: 'categories', label: 'categories', cx: 4600, cy: 250,
    fill: '#e1d5e7', stroke: '#9673a6', attrDir: 'above',
    attrs: [
      { name: 'category_id', pk: true },
      { name: 'category_name' },
      { name: 'price' },
      { name: 'max_duration' },
      { name: 'max_size' },
      { name: 'discount_type' },
      { name: 'discount_value' }
    ]
  },
  { id: 'advertisements', label: 'advertisements', cx: 4600, cy: 950,
    fill: '#e1d5e7', stroke: '#9673a6', attrDir: 'above',
    attrs: [
      { name: 'ad_id', pk: true },
      { name: 'advertiser_id', fk: true },
      { name: 'category_id', fk: true },
      { name: 'title' },
      { name: 'file_path' },
      { name: 'duration' },
      { name: 'file_size' },
      { name: 'status' },
      { name: 'rejection_reason' },
      { name: 'uploaded_at' }
    ]
  },
  { id: 'ad_screens', label: 'ad_screens', cx: 3900, cy: 1400,
    fill: '#e1d5e7', stroke: '#9673a6', attrDir: 'below',
    attrs: [
      { name: 'ad_screen_id', pk: true },
      { name: 'ad_id', fk: true },
      { name: 'screen_id', fk: true },
      { name: 'price' }
    ]
  },
  { id: 'ad_schedules', label: 'ad_schedules', cx: 5400, cy: 950,
    fill: '#e1d5e7', stroke: '#9673a6', attrDir: 'above',
    attrs: [
      { name: 'schedule_id', pk: true },
      { name: 'ad_id', fk: true },
      { name: 'start_date' },
      { name: 'end_date' },
      { name: 'start_time' },
      { name: 'end_time' },
      { name: 'is_active' }
    ]
  },
  { id: 'playback_logs', label: 'playback_logs', cx: 5400, cy: 1600,
    fill: '#e1d5e7', stroke: '#9673a6', attrDir: 'below',
    attrs: [
      { name: 'log_id', pk: true },
      { name: 'ad_id', fk: true },
      { name: 'screen_id', fk: true },
      { name: 'played_at' },
      { name: 'duration_played' }
    ]
  },

  /* ── FINANCE ──────────────────────────────────────────────────────── */
  { id: 'invoices', label: 'invoices', cx: 6300, cy: 250,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'above',
    attrs: [
      { name: 'invoice_id', pk: true },
      { name: 'advertiser_id', fk: true },
      { name: 'invoice_number' },
      { name: 'total_amount' },
      { name: 'discount_amount' },
      { name: 'final_amount' },
      { name: 'status' },
      { name: 'issue_date' },
      { name: 'due_date' }
    ]
  },
  { id: 'invoice_items', label: 'invoice_items', cx: 5900, cy: 950,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'above',
    attrs: [
      { name: 'item_id', pk: true },
      { name: 'invoice_id', fk: true },
      { name: 'ad_id', fk: true },
      { name: 'item_price' },
      { name: 'quantity' },
      { name: 'subtotal' }
    ]
  },
  { id: 'transactions', label: 'transactions', cx: 6700, cy: 950,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'above',
    attrs: [
      { name: 'transaction_id', pk: true },
      { name: 'invoice_id', fk: true },
      { name: 'payment_method' },
      { name: 'amount_paid' },
      { name: 'payment_status' },
      { name: 'payment_date' },
      { name: 'reference_number' }
    ]
  },
  { id: 'wallets', label: 'wallets', cx: 6300, cy: 1600,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'above',
    attrs: [
      { name: 'wallet_id', pk: true },
      { name: 'user_id', fk: true },
      { name: 'available_balance' },
      { name: 'pending_balance' },
      { name: 'total_earned' },
      { name: 'total_withdrawn' }
    ]
  },
  { id: 'wallet_transactions', label: 'wallet_transactions', cx: 6300, cy: 2200,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'below',
    attrs: [
      { name: 'wallet_tx_id', pk: true },
      { name: 'wallet_id', fk: true },
      { name: 'transaction_type' },
      { name: 'amount' },
      { name: 'description' },
      { name: 'reference_id' },
      { name: 'created_at' }
    ]
  },
  { id: 'bank_accounts', label: 'bank_accounts', cx: 5700, cy: 2100,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'below',
    attrs: [
      { name: 'account_id', pk: true },
      { name: 'user_id', fk: true },
      { name: 'bank_name' },
      { name: 'account_number' },
      { name: 'iban' },
      { name: 'swift_code' },
      { name: 'is_default' },
      { name: 'is_verified' }
    ]
  },
  { id: 'financial_ledgers', label: 'financial_ledgers', cx: 6900, cy: 2100,
    fill: '#ffe6cc', stroke: '#d79b00', attrDir: 'below',
    attrs: [
      { name: 'ledger_id', pk: true },
      { name: 'advertisement_id', fk: true },
      { name: 'screen_id', fk: true },
      { name: 'user_id', fk: true },
      { name: 'transaction_type' },
      { name: 'amount' },
      { name: 'status' },
      { name: 'notes' },
      { name: 'created_at' }
    ]
  },

  /* ── SUPPORT / CONFIG ─────────────────────────────────────────────── */
  { id: 'notifications', label: 'notifications', cx: 7700, cy: 250,
    fill: '#d5e8d4', stroke: '#82b366', attrDir: 'above',
    attrs: [
      { name: 'notification_id', pk: true },
      { name: 'user_id', fk: true },
      { name: 'title' },
      { name: 'message' },
      { name: 'type' },
      { name: 'is_read' },
      { name: 'created_at' }
    ]
  },
  { id: 'support_tickets', label: 'support_tickets', cx: 7700, cy: 950,
    fill: '#d5e8d4', stroke: '#82b366', attrDir: 'above',
    attrs: [
      { name: 'id', pk: true },
      { name: 'user_id', fk: true },
      { name: 'subject' },
      { name: 'description' },
      { name: 'priority' },
      { name: 'status' },
      { name: 'created_at' },
      { name: 'resolved_at' }
    ]
  },
  { id: 'payment_methods', label: 'payment_methods', cx: 7700, cy: 1600,
    fill: '#d5e8d4', stroke: '#82b366', attrDir: 'below',
    attrs: [
      { name: 'method_id', pk: true },
      { name: 'name' },
      { name: 'account_details' },
      { name: 'is_active' }
    ]
  },
  { id: 'duration_discounts', label: 'duration_discounts', cx: 7700, cy: 2100,
    fill: '#d5e8d4', stroke: '#82b366', attrDir: 'below',
    attrs: [
      { name: 'duration_discount_id', pk: true },
      { name: 'name' },
      { name: 'min_days' },
      { name: 'max_days' },
      { name: 'discount_percentage' },
      { name: 'is_active' }
    ]
  },
  { id: 'system_settings', label: 'system_settings', cx: 7700, cy: 2600,
    fill: '#d5e8d4', stroke: '#82b366', attrDir: 'below',
    attrs: [
      { name: 'setting_id', pk: true },
      { name: 'setting_key' },
      { name: 'setting_value' },
      { name: 'description' }
    ]
  }
];

// ═══════════════════════════════════════════════════════════════════════════
// RELATIONSHIP DEFINITIONS
// ═══════════════════════════════════════════════════════════════════════════
const RELATIONSHIPS = [
  // AUTH
  { id: 'r_has_role',    label: 'يمتلك',      cx: 720,  cy: 580,  fill: '#fff2cc', stroke: '#d6b656',
    from: 'roles',           fromCard: '1', to: 'users',             toCard: 'N', relType: '1:N' },
  { id: 'r_has_sess',    label: 'يملك',       cx: 720,  cy: 1000, fill: '#fff2cc', stroke: '#d6b656',
    from: 'users',           fromCard: '1', to: 'user_sessions',      toCard: 'N', relType: '1:N' },

  // LOCATION
  { id: 'r_in_reg',      label: 'تحتوي',      cx: 2000, cy: 540,  fill: '#fff2cc', stroke: '#d6b656',
    from: 'governorates',    fromCard: '1', to: 'regions',            toCard: 'N', relType: '1:N' },
  { id: 'r_in_str',      label: 'تحتوي',      cx: 2000, cy: 1050, fill: '#fff2cc', stroke: '#d6b656',
    from: 'regions',         fromCard: '1', to: 'streets',            toCard: 'N', relType: '1:N' },

  // SCREENS
  { id: 'r_is_type',     label: 'نوعه',       cx: 2900, cy: 560,  fill: '#f8cecc', stroke: '#b85450',
    from: 'screen_types',    fromCard: '1', to: 'screens',            toCard: 'N', relType: '1:N' },
  { id: 'r_owns_sc',     label: 'يمتلك',      cx: 1970, cy: 900,  fill: '#f8cecc', stroke: '#b85450',
    from: 'users',           fromCard: '1', to: 'screens',            toCard: 'N', relType: '1:N' },
  { id: 'r_on_str',      label: 'يقع في',     cx: 2450, cy: 1100, fill: '#f8cecc', stroke: '#b85450',
    from: 'streets',         fromCard: '1', to: 'screens',            toCard: 'N', relType: '1:N' },
  { id: 'r_has_slot',    label: 'له سعر',     cx: 2900, cy: 1250, fill: '#f8cecc', stroke: '#b85450',
    from: 'screens',         fromCard: '1', to: 'screen_pricing_slots', toCard: 'N', relType: '1:N' },
  { id: 'r_sc_cmd',      label: 'يستقبل أمر\n(via mac_address)', cx: 3300, cy: 1350, fill: '#f8cecc', stroke: '#b85450',
    from: 'screens',         fromCard: '1', to: 'screen_commands',      toCard: 'N', relType: '1:N' },

  // ADS
  { id: 'r_in_cat',      label: 'تصنيف',      cx: 4600, cy: 580,  fill: '#e1d5e7', stroke: '#9673a6',
    from: 'categories',      fromCard: '1', to: 'advertisements',     toCard: 'N', relType: '1:N' },
  { id: 'r_posts',       label: 'ينشر',       cx: 2830, cy: 950,  fill: '#e1d5e7', stroke: '#9673a6',
    from: 'users',           fromCard: '1', to: 'advertisements',     toCard: 'N', relType: '1:N' },
  { id: 'r_ad_adsc',     label: 'يعرض على',   cx: 4250, cy: 1200, fill: '#e1d5e7', stroke: '#9673a6',
    from: 'advertisements',  fromCard: 'M', to: 'ad_screens',          toCard: 'N', relType: 'M:N' },
  { id: 'r_sc_adsc',     label: 'تعرض',       cx: 3400, cy: 1200, fill: '#e1d5e7', stroke: '#9673a6',
    from: 'screens',         fromCard: 'M', to: 'ad_screens',          toCard: 'N', relType: 'M:N' },
  { id: 'r_has_sch',     label: 'جدول',       cx: 5000, cy: 950,  fill: '#e1d5e7', stroke: '#9673a6',
    from: 'advertisements',  fromCard: '1', to: 'ad_schedules',        toCard: 'N', relType: '1:N' },
  { id: 'r_log_ad',      label: 'سجل تشغيل', cx: 5000, cy: 1250, fill: '#e1d5e7', stroke: '#9673a6',
    from: 'advertisements',  fromCard: '1', to: 'playback_logs',       toCard: 'N', relType: '1:N' },
  { id: 'r_log_sc',      label: 'على',        cx: 4150, cy: 1450, fill: '#e1d5e7', stroke: '#9673a6',
    from: 'screens',         fromCard: '1', to: 'playback_logs',       toCard: 'N', relType: '1:N' },

  // FINANCE
  { id: 'r_billed',      label: 'فاتورة',     cx: 3700, cy: 250,  fill: '#ffe6cc', stroke: '#d79b00',
    from: 'users',           fromCard: '1', to: 'invoices',            toCard: 'N', relType: '1:N' },
  { id: 'r_item_of',     label: 'بنود',       cx: 6100, cy: 580,  fill: '#ffe6cc', stroke: '#d79b00',
    from: 'invoices',        fromCard: '1', to: 'invoice_items',       toCard: 'N', relType: '1:N' },
  { id: 'r_ad_item',     label: 'ضمن',        cx: 5250, cy: 950,  fill: '#ffe6cc', stroke: '#d79b00',
    from: 'advertisements',  fromCard: '1', to: 'invoice_items',       toCard: 'N', relType: '1:N' },
  { id: 'r_pays',        label: 'دفع',        cx: 6500, cy: 580,  fill: '#ffe6cc', stroke: '#d79b00',
    from: 'invoices',        fromCard: '1', to: 'transactions',        toCard: 'N', relType: '1:N' },
  { id: 'r_has_wal',     label: 'محفظة',      cx: 3700, cy: 1600, fill: '#ffe6cc', stroke: '#d79b00',
    from: 'users',           fromCard: '1', to: 'wallets',             toCard: '1', relType: '1:1' },
  { id: 'r_waltx_of',   label: 'عملية',      cx: 6300, cy: 1900, fill: '#ffe6cc', stroke: '#d79b00',
    from: 'wallets',         fromCard: '1', to: 'wallet_transactions', toCard: 'N', relType: '1:N' },
  { id: 'r_bank_of',    label: 'حساب بنكي',  cx: 3500, cy: 2100, fill: '#ffe6cc', stroke: '#d79b00',
    from: 'users',           fromCard: '1', to: 'bank_accounts',       toCard: 'N', relType: '1:N' },
  { id: 'r_fl_ad',       label: 'سجل مالي',  cx: 5800, cy: 1600, fill: '#ffe6cc', stroke: '#d79b00',
    from: 'advertisements',  fromCard: '1', to: 'financial_ledgers',   toCard: 'N', relType: '1:N' },
  { id: 'r_fl_sc',       label: 'للشاشة',    cx: 4900, cy: 1800, fill: '#ffe6cc', stroke: '#d79b00',
    from: 'screens',         fromCard: '1', to: 'financial_ledgers',   toCard: 'N', relType: '1:N' },
  { id: 'r_fl_us',       label: 'للمستخدم',  cx: 4100, cy: 1900, fill: '#ffe6cc', stroke: '#d79b00',
    from: 'users',           fromCard: '1', to: 'financial_ledgers',   toCard: 'N', relType: '1:N' },

  // SUPPORT
  { id: 'r_notif',       label: 'إشعار',      cx: 4650, cy: 250,  fill: '#d5e8d4', stroke: '#82b366',
    from: 'users',           fromCard: '1', to: 'notifications',       toCard: 'N', relType: '1:N' },
  { id: 'r_ticket',      label: 'تذكرة',      cx: 4650, cy: 900,  fill: '#d5e8d4', stroke: '#82b366',
    from: 'users',           fromCard: '1', to: 'support_tickets',     toCard: 'N', relType: '1:N' },
];

// ═══════════════════════════════════════════════════════════════════════════
// GENERATOR
// ═══════════════════════════════════════════════════════════════════════════
const EW = 200, EH = 55;
const AW = 100, AH = 37;
const RW = 120, RH = 70;
const MAX_PER_ROW = 5;
const COL_SPACING = 112;
const ROW_SPACING = 55;

let uid = 2000;
const getId = () => `x${uid++}`;
const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function positionAttrs(attrs, cx, cy, dir) {
  const result = [];
  for (let i = 0; i < attrs.length; i++) {
    const row = Math.floor(i / MAX_PER_ROW);
    const col = i % MAX_PER_ROW;
    const countInRow = Math.min(MAX_PER_ROW, attrs.length - row * MAX_PER_ROW);
    const rowStartX = cx - ((countInRow - 1) * COL_SPACING) / 2;
    const ax = rowStartX + col * COL_SPACING;
    let ay;
    if (dir === 'above') {
      // row 0 = closest to entity, going UP
      ay = cy - EH / 2 - 80 - row * ROW_SPACING;
    } else {
      // row 0 = closest to entity, going DOWN
      ay = cy + EH / 2 + 55 + row * ROW_SPACING;
    }
    result.push({ ...attrs[i], ax, ay });
  }
  return result;
}

let lines = [];
const L = s => lines.push(s);

L(`<?xml version="1.0" encoding="UTF-8"?>`);
L(`<mxGraphModel dx="6000" dy="6000" grid="0" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="9000" pageHeight="3200" math="0" shadow="0">`);
L(`  <root>`);
L(`    <mxCell id="0"/>`);
L(`    <mxCell id="1" parent="0"/>`);
L(``);

// ── Legend ──
L(`    <!-- ====== LEGEND ====== -->`);
L(`    <mxCell id="legend_bg" value="" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#f5f5f5;strokeColor=#666666;fontColor=#333333;" vertex="1" parent="1"><mxGeometry x="20" y="20" width="340" height="190" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_t" value="&lt;b&gt;مفتاح المخطط (Legend)&lt;/b&gt;" style="text;html=1;align=center;fontSize=13;" vertex="1" parent="1"><mxGeometry x="20" y="22" width="340" height="30" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_e" value="كيان (Entity)" style="rounded=0;fontStyle=1;fontSize=11;fillColor=#dae8fc;strokeColor=#6c8ebf;" vertex="1" parent="1"><mxGeometry x="35" y="60" width="120" height="32" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_a" value="صفة (Attribute)" style="ellipse;fontStyle=0;fontSize=11;fillColor=#dae8fc;strokeColor=#6c8ebf;" vertex="1" parent="1"><mxGeometry x="195" y="58" width="145" height="36" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_pk" value="مفتاح أساسي PK" style="ellipse;fontStyle=4;fontSize=11;fillColor=#fff2cc;strokeColor=#d6b656;" vertex="1" parent="1"><mxGeometry x="35" y="105" width="145" height="36" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_fk" value="مفتاح أجنبي FK" style="ellipse;fontStyle=0;fontSize=11;fillColor=#f8cecc;strokeColor=#b85450;dashed=1;" vertex="1" parent="1"><mxGeometry x="195" y="105" width="145" height="36" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_r" value="علاقة (Relationship)" style="rhombus;fontStyle=1;fontSize=11;fillColor=#e1d5e7;strokeColor=#9673a6;" vertex="1" parent="1"><mxGeometry x="35" y="152" width="190" height="50" as="geometry"/></mxCell>`);
L(`    <mxCell id="leg_card" value="&lt;b&gt;1  N  M&lt;/b&gt; = قوة العلاقة" style="text;html=1;align=center;fontSize=11;" vertex="1" parent="1"><mxGeometry x="230" y="160" width="110" height="35" as="geometry"/></mxCell>`);
L(``);

// ── Entities ──
for (const e of ENTITIES) {
  const ex = e.cx - EW / 2, ey = e.cy - EH / 2;
  L(`    <!-- ====== ${e.label.toUpperCase()} ====== -->`);
  L(`    <mxCell id="e_${e.id}" value="${esc(e.label)}" style="rounded=0;whiteSpace=wrap;html=1;fontStyle=1;fontSize=14;fillColor=${e.fill};strokeColor=${e.stroke};" vertex="1" parent="1"><mxGeometry x="${ex}" y="${ey}" width="${EW}" height="${EH}" as="geometry"/></mxCell>`);

  const positioned = positionAttrs(e.attrs, e.cx, e.cy, e.attrDir);
  for (const a of positioned) {
    const aId = getId();
    const fontStyle = a.pk ? 4 : 0;
    const dashed = a.fk ? 'dashed=1;' : '';
    const ax = Math.round(a.ax - AW / 2), ay = Math.round(a.ay - AH / 2);
    L(`    <mxCell id="${aId}" value="${esc(a.name)}" style="ellipse;whiteSpace=wrap;html=1;fontStyle=${fontStyle};fontSize=11;fillColor=${e.fill};strokeColor=${e.stroke};${dashed}" vertex="1" parent="1"><mxGeometry x="${ax}" y="${ay}" width="${AW}" height="${AH}" as="geometry"/></mxCell>`);
    const edId = getId();
    L(`    <mxCell id="${edId}" edge="1" source="e_${e.id}" target="${aId}" parent="1" style="endArrow=none;startArrow=none;exitX=0.5;exitY=0.5;entryX=0.5;entryY=0.5;edgeStyle=none;"><mxGeometry relative="1" as="geometry"/></mxCell>`);
  }
  L(``);
}

// ── Relationships ──
for (const r of RELATIONSHIPS) {
  const rx = Math.round(r.cx - RW / 2), ry = Math.round(r.cy - RH / 2);
  // Full label: relationship name + type on second line
  const rLabel = `${r.label}\\n(${r.relType})`;

  L(`    <!-- REL: ${r.from} to ${r.to} (${r.relType}) -->`);
  L(`    <mxCell id="${r.id}" value="${esc(r.label)}&lt;br/&gt;&lt;font style=&quot;font-size:10px;&quot;&gt;(${r.relType})&lt;/font&gt;" style="rhombus;whiteSpace=wrap;html=1;fontStyle=1;fontSize=12;fillColor=${r.fill};strokeColor=${r.stroke};verticalAlign=middle;" vertex="1" parent="1"><mxGeometry x="${rx}" y="${ry}" width="${RW}" height="${RH}" as="geometry"/></mxCell>`);

  // Edge: from entity → relationship  (label = fromCard near entity)
  const e1Id = getId();
  L(`    <mxCell id="${e1Id}" edge="1" source="e_${r.from}" target="${r.id}" parent="1" style="endArrow=none;startArrow=none;edgeStyle=none;"><mxGeometry relative="1" as="geometry"/></mxCell>`);
  // Cardinality label near SOURCE entity
  const lbl1Id = getId();
  L(`    <mxCell id="${lbl1Id}" value="&lt;b&gt;${esc(r.fromCard)}&lt;/b&gt;" style="edgeLabel;html=1;align=center;verticalAlign=middle;resizable=0;fontSize=14;fontColor=#CC0000;" vertex="1" connectable="0" parent="${e1Id}"><mxGeometry x="-0.75" relative="1" as="geometry"><mxPoint as="offset"/></mxGeometry></mxCell>`);

  // Edge: from relationship → target entity  (label = toCard near target)
  const e2Id = getId();
  L(`    <mxCell id="${e2Id}" edge="1" source="${r.id}" target="e_${r.to}" parent="1" style="endArrow=none;startArrow=none;edgeStyle=none;"><mxGeometry relative="1" as="geometry"/></mxCell>`);
  // Cardinality label near TARGET entity
  const lbl2Id = getId();
  L(`    <mxCell id="${lbl2Id}" value="&lt;b&gt;${esc(r.toCard)}&lt;/b&gt;" style="edgeLabel;html=1;align=center;verticalAlign=middle;resizable=0;fontSize=14;fontColor=#CC0000;" vertex="1" connectable="0" parent="${e2Id}"><mxGeometry x="0.75" relative="1" as="geometry"><mxPoint as="offset"/></mxGeometry></mxCell>`);
  L(``);
}

L(`  </root>`);
L(`</mxGraphModel>`);

const output = lines.join('\n');
const outPath = 'd:/projects/laravel/sabapost/database_erd_chen_full.drawio';
fs.writeFileSync(outPath, output, 'utf8');

// Stats
const entityCount = ENTITIES.length;
const attrCount = ENTITIES.reduce((s, e) => s + e.attrs.length, 0);
const relCount = RELATIONSHIPS.length;
const edgeCount = relCount * 2;

console.log('✅  File written:', outPath);
console.log(`📦  Entities:        ${entityCount}`);
console.log(`🔵  Attributes:      ${attrCount}`);
console.log(`🔶  Relationships:   ${relCount}`);
console.log(`➖  Edges (total):   ${attrCount + edgeCount}`);
console.log(`📄  File size:       ${(output.length / 1024).toFixed(1)} KB`);
