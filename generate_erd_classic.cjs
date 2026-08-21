/**
 * Classic ERD Generator v2 - SabaPost System
 * Creates a professional database schema diagram (similar to reference image)
 * Uses Draw.io table shapes with crow's foot notation
 * FIX: collapsible=1, proper spacing, correct edge styles
 */
const fs = require('fs');

const ROW_H = 24;
const HDR_H = 30;
const LBL_W = 36;

const esc = s => String(s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ── Zone color palette ────────────────────────────────────────
const Z = {
  auth:     { hdr:'#4A7EC7', row:'#EEF5FF', bg:'#D6E8FC', stroke:'#3A6EA8', txt:'#FFFFFF' },
  location: { hdr:'#C49A00', row:'#FFFBEC', bg:'#FFF5CC', stroke:'#9A7800', txt:'#FFFFFF' },
  screens:  { hdr:'#C0564B', row:'#FFF0EF', bg:'#FFE5E3', stroke:'#963A30', txt:'#FFFFFF' },
  ads:      { hdr:'#7E57C2', row:'#F5F0FB', bg:'#EDE0F8', stroke:'#5E35B1', txt:'#FFFFFF' },
  finance:  { hdr:'#D17A00', row:'#FFF8F0', bg:'#FFE8CC', stroke:'#A85E00', txt:'#FFFFFF' },
  support:  { hdr:'#5A9B60', row:'#F0FBF1', bg:'#D5EDCE', stroke:'#3A7A40', txt:'#FFFFFF' },
};

// ── Table definitions ─────────────────────────────────────────
const TABLES = [

  /* AUTH ─────────────────────────────────────────────────────── */
  { id:'roles',               z:'auth',     x:50,   y:50,  w:200,
    cols:[ {k:'PK',n:'role_id'}, {k:'',n:'role_name'} ]
  },
  { id:'users',               z:'auth',     x:50,   y:170, w:240,
    cols:[
      {k:'PK',n:'user_id'},   {k:'FK',n:'role_id'},
      {k:'',n:'full_name'},   {k:'',n:'email'},
      {k:'',n:'phone'},       {k:'',n:'location'},
      {k:'',n:'password_hash'},{k:'',n:'account_status'},
      {k:'',n:'created_at'},  {k:'',n:'deleted_at'},
    ]
  },
  { id:'user_sessions',       z:'auth',     x:330,  y:50,  w:240,
    cols:[
      {k:'PK',n:'session_id'},{k:'FK',n:'user_id'},
      {k:'',n:'device_name'},{k:'',n:'device_id'},
      {k:'',n:'ip_address'}, {k:'',n:'fcm_token'},
      {k:'',n:'last_active'},{k:'',n:'is_revoked'},
    ]
  },

  /* LOCATION ─────────────────────────────────────────────────── */
  { id:'governorates',        z:'location', x:630,  y:50,  w:200,
    cols:[ {k:'PK',n:'gov_id'}, {k:'',n:'name'} ]
  },
  { id:'regions',             z:'location', x:630,  y:180, w:200,
    cols:[ {k:'PK',n:'region_id'}, {k:'FK',n:'gov_id'}, {k:'',n:'name'} ]
  },
  { id:'streets',             z:'location', x:630,  y:360, w:200,
    cols:[ {k:'PK',n:'street_id'}, {k:'FK',n:'region_id'}, {k:'',n:'name'} ]
  },

  /* SCREENS ──────────────────────────────────────────────────── */
  { id:'screen_types',        z:'screens',  x:895,  y:50,  w:220,
    cols:[
      {k:'PK',n:'type_id'},        {k:'',n:'type_name'},
      {k:'',n:'resolution_width'}, {k:'',n:'resolution_height'},
      {k:'',n:'orientation'},
    ]
  },
  { id:'screens',             z:'screens',  x:895,  y:260, w:250,
    cols:[
      {k:'PK',n:'screen_id'},         {k:'FK',n:'owner_id → users'},
      {k:'FK',n:'type_id'},           {k:'FK',n:'street_id'},
      {k:'FK',n:'linked_by → users'}, {k:'',n:'screen_name'},
      {k:'',n:'status'},              {k:'',n:'mac_address'},
      {k:'',n:'installation_date'},
    ]
  },
  { id:'screen_pricing_slots',z:'screens',  x:895,  y:570, w:250,
    cols:[
      {k:'PK',n:'slot_id'},{k:'FK',n:'screen_id'},
      {k:'',n:'start_time'},{k:'',n:'end_time'},{k:'',n:'price_multiplier'},
    ]
  },
  { id:'screen_commands',     z:'screens',  x:1210, y:50,  w:250,
    cols:[
      {k:'PK',n:'id'},
      {k:'',n:'target_screen (mac / all)'},
      {k:'',n:'command'},
      {k:'',n:'created_at'},
    ]
  },

  /* ADS ──────────────────────────────────────────────────────── */
  { id:'categories',          z:'ads',      x:1530, y:50,  w:220,
    cols:[
      {k:'PK',n:'category_id'},{k:'',n:'category_name'},
      {k:'',n:'price'},        {k:'',n:'max_duration'},
      {k:'',n:'max_size'},     {k:'',n:'discount_type'},
      {k:'',n:'discount_value'},
    ]
  },
  { id:'advertisements',      z:'ads',      x:1530, y:320, w:260,
    cols:[
      {k:'PK',n:'ad_id'},                   {k:'FK',n:'advertiser_id → users'},
      {k:'FK',n:'category_id'},             {k:'FK',n:'approved_by → users'},
      {k:'',n:'title'},                     {k:'',n:'file_path'},
      {k:'',n:'duration'},                  {k:'',n:'file_size'},
      {k:'',n:'status'},                    {k:'',n:'rejection_reason'},
      {k:'',n:'uploaded_at'},
    ]
  },
  { id:'ad_screens',          z:'ads',      x:1530, y:750, w:220,
    cols:[
      {k:'PK',n:'ad_screen_id'},{k:'FK',n:'ad_id'},
      {k:'FK',n:'screen_id'},   {k:'',n:'price'},
    ]
  },
  { id:'ad_schedules',        z:'ads',      x:1860, y:320, w:220,
    cols:[
      {k:'PK',n:'schedule_id'},{k:'FK',n:'ad_id'},
      {k:'',n:'start_date'},{k:'',n:'end_date'},
      {k:'',n:'start_time'},{k:'',n:'end_time'},{k:'',n:'is_active'},
    ]
  },
  { id:'playback_logs',       z:'ads',      x:1860, y:590, w:220,
    cols:[
      {k:'PK',n:'log_id'},{k:'FK',n:'ad_id'},
      {k:'FK',n:'screen_id'},{k:'',n:'played_at'},{k:'',n:'duration_played'},
    ]
  },

  /* FINANCE ──────────────────────────────────────────────────── */
  { id:'invoices',            z:'finance',  x:2160, y:50,  w:250,
    cols:[
      {k:'PK',n:'invoice_id'},          {k:'FK',n:'advertiser_id → users'},
      {k:'',n:'invoice_number'},        {k:'',n:'total_amount'},
      {k:'',n:'discount_amount'},       {k:'',n:'final_amount'},
      {k:'',n:'status'},                {k:'',n:'issue_date'},
      {k:'',n:'due_date'},
    ]
  },
  { id:'invoice_items',       z:'finance',  x:2160, y:400, w:230,
    cols:[
      {k:'PK',n:'item_id'},   {k:'FK',n:'invoice_id'},
      {k:'FK',n:'ad_id'},     {k:'',n:'item_price'},
      {k:'',n:'quantity'},    {k:'',n:'subtotal'},
    ]
  },
  { id:'transactions',        z:'finance',  x:2160, y:630, w:230,
    cols:[
      {k:'PK',n:'transaction_id'},{k:'FK',n:'invoice_id'},
      {k:'',n:'payment_method'}, {k:'',n:'amount_paid'},
      {k:'',n:'payment_status'}, {k:'',n:'payment_date'},
      {k:'',n:'reference_number'},
    ]
  },
  { id:'wallets',             z:'finance',  x:2470, y:50,  w:240,
    cols:[
      {k:'PK',n:'wallet_id'},     {k:'FK',n:'user_id'},
      {k:'',n:'available_balance'},{k:'',n:'pending_balance'},
      {k:'',n:'total_earned'},    {k:'',n:'total_withdrawn'},
    ]
  },
  { id:'wallet_transactions', z:'finance',  x:2470, y:320, w:260,
    cols:[
      {k:'PK',n:'wallet_tx_id'}, {k:'FK',n:'wallet_id'},
      {k:'',n:'transaction_type'},{k:'',n:'amount'},
      {k:'',n:'description'},    {k:'',n:'reference_id'},
      {k:'',n:'created_at'},
    ]
  },
  { id:'bank_accounts',       z:'finance',  x:2470, y:580, w:240,
    cols:[
      {k:'PK',n:'account_id'},{k:'FK',n:'user_id'},
      {k:'',n:'bank_name'},   {k:'',n:'account_number'},
      {k:'',n:'iban'},         {k:'',n:'swift_code'},
      {k:'',n:'is_default'},  {k:'',n:'is_verified'},
    ]
  },
  { id:'financial_ledgers',   z:'finance',  x:2470, y:850, w:260,
    cols:[
      {k:'PK',n:'ledger_id'},       {k:'FK',n:'advertisement_id'},
      {k:'FK',n:'screen_id'},       {k:'FK',n:'user_id'},
      {k:'',n:'transaction_type'},  {k:'',n:'amount'},
      {k:'',n:'status'},            {k:'',n:'notes'},
      {k:'',n:'created_at'},
    ]
  },

  /* SUPPORT / CONFIG ─────────────────────────────────────────── */
  { id:'notifications',       z:'support',  x:2810, y:50,  w:240,
    cols:[
      {k:'PK',n:'notification_id'},{k:'FK',n:'user_id'},
      {k:'',n:'title'},           {k:'',n:'message'},
      {k:'',n:'type'},            {k:'',n:'is_read'},
      {k:'',n:'created_at'},
    ]
  },
  { id:'support_tickets',     z:'support',  x:2810, y:340, w:240,
    cols:[
      {k:'PK',n:'id'},        {k:'FK',n:'user_id'},
      {k:'',n:'subject'},     {k:'',n:'description'},
      {k:'',n:'priority'},    {k:'',n:'status'},
      {k:'',n:'created_at'}, {k:'',n:'resolved_at'},
    ]
  },
  { id:'payment_methods',     z:'support',  x:2810, y:640, w:220,
    cols:[
      {k:'PK',n:'method_id'},{k:'',n:'name'},
      {k:'',n:'account_details'},{k:'',n:'is_active'},
    ]
  },
  { id:'duration_discounts',  z:'support',  x:2810, y:800, w:250,
    cols:[
      {k:'PK',n:'duration_discount_id'},{k:'',n:'name'},
      {k:'',n:'min_days'},             {k:'',n:'max_days'},
      {k:'',n:'discount_percentage'},  {k:'',n:'is_active'},
    ]
  },
  { id:'system_settings',     z:'support',  x:2810, y:1060, w:220,
    cols:[
      {k:'PK',n:'setting_id'}, {k:'',n:'setting_key'},
      {k:'',n:'setting_value'},{k:'',n:'description'},
    ]
  },
];

// ── Relationships ─────────────────────────────────────────────
const RELS = [
  // Auth
  { s:'roles',          t:'users',                 c:'1:N' },
  { s:'users',          t:'user_sessions',          c:'1:N' },
  // Location
  { s:'governorates',   t:'regions',                c:'1:N' },
  { s:'regions',        t:'streets',                c:'1:N' },
  // Screens
  { s:'screen_types',   t:'screens',                c:'1:N' },
  { s:'users',          t:'screens',                c:'1:N' },
  { s:'streets',        t:'screens',                c:'1:N' },
  { s:'screens',        t:'screen_pricing_slots',   c:'1:N' },
  { s:'screens',        t:'screen_commands',        c:'1:N', dashed:true },
  // Ads
  { s:'categories',     t:'advertisements',         c:'1:N' },
  { s:'users',          t:'advertisements',         c:'1:N' },
  { s:'advertisements', t:'ad_screens',             c:'1:N' },
  { s:'screens',        t:'ad_screens',             c:'1:N' },
  { s:'advertisements', t:'ad_schedules',           c:'1:N' },
  { s:'advertisements', t:'playback_logs',          c:'1:N' },
  { s:'screens',        t:'playback_logs',          c:'1:N' },
  // Finance
  { s:'users',          t:'invoices',               c:'1:N' },
  { s:'invoices',       t:'invoice_items',          c:'1:N' },
  { s:'advertisements', t:'invoice_items',          c:'1:N' },
  { s:'invoices',       t:'transactions',           c:'1:N' },
  { s:'users',          t:'wallets',                c:'1:1' },
  { s:'wallets',        t:'wallet_transactions',    c:'1:N' },
  { s:'users',          t:'bank_accounts',          c:'1:N' },
  { s:'advertisements', t:'financial_ledgers',      c:'1:N' },
  { s:'screens',        t:'financial_ledgers',      c:'1:N' },
  { s:'users',          t:'financial_ledgers',      c:'1:N' },
  // Support
  { s:'users',          t:'notifications',          c:'1:N' },
  { s:'users',          t:'support_tickets',        c:'1:N' },
];

// ── XML Generators ────────────────────────────────────────────
function tableH(t) { return HDR_H + t.cols.length * ROW_H; }

function genTable(t) {
  const zc = Z[t.z];
  const h  = tableH(t);
  const nw = t.w - LBL_W;
  let s = '';

  s += `    <mxCell id="t_${t.id}" value="${esc(t.id)}" `
     + `style="shape=table;startSize=${HDR_H};container=1;collapsible=1;`
     + `childLayout=tableLayout;fixedRows=1;rowLines=0;fontStyle=1;align=center;`
     + `resizeLast=1;fillColor=${zc.hdr};strokeColor=${zc.stroke};`
     + `fontColor=${zc.txt};fontSize=13;shadow=0;" `
     + `vertex="1" parent="1">`
     + `<mxGeometry x="${t.x}" y="${t.y}" width="${t.w}" height="${h}" as="geometry"/>`
     + `</mxCell>\n`;

  for (let i = 0; i < t.cols.length; i++) {
    const c   = t.cols[i];
    const rid = `r_${t.id}_${i}`;
    const ry  = HDR_H + i * ROW_H;
    const bot = i === t.cols.length - 1 ? 0 : 1;
    const rfill = c.k === 'PK' ? '#FFF9C4' : zc.row;

    s += `    <mxCell id="${rid}" value="" `
       + `style="shape=tableRow;horizontal=0;startSize=0;swimlaneHead=0;swimlaneBody=0;`
       + `fillColor=${rfill};collapsible=0;dropTarget=0;`
       + `points=[[0,0.5],[1,0.5]];portConstraint=eastwest;`
       + `fontSize=11;top=0;left=0;right=0;bottom=${bot};" `
       + `vertex="1" parent="t_${t.id}">`
       + `<mxGeometry y="${ry}" width="${t.w}" height="${ROW_H}" as="geometry"/>`
       + `</mxCell>\n`;

    const kst = c.k === 'PK'
      ? 'fontStyle=1;fontColor=#7A6000;'
      : c.k === 'FK'
        ? 'fontStyle=2;fontColor=#4A148C;'
        : '';
    s += `    <mxCell id="${rid}k" value="${esc(c.k)}" `
       + `style="shape=partialRectangle;connectable=0;fillColor=none;`
       + `top=0;left=0;bottom=0;right=0;${kst}overflow=hidden;fontSize=10;" `
       + `vertex="1" parent="${rid}">`
       + `<mxGeometry width="${LBL_W}" height="${ROW_H}" as="geometry">`
       + `<mxRectangle width="${LBL_W}" height="${ROW_H}" as="alternateBounds"/>`
       + `</mxGeometry></mxCell>\n`;

    s += `    <mxCell id="${rid}n" value="${esc(c.n)}" `
       + `style="shape=partialRectangle;connectable=0;fillColor=none;`
       + `top=0;left=0;bottom=0;right=0;overflow=hidden;fontSize=11;" `
       + `vertex="1" parent="${rid}">`
       + `<mxGeometry x="${LBL_W}" width="${nw}" height="${ROW_H}" as="geometry">`
       + `<mxRectangle width="${nw}" height="${ROW_H}" as="alternateBounds"/>`
       + `</mxGeometry></mxCell>\n`;
  }
  return s;
}

function genRel(r, i) {
  let sa, ea;
  if      (r.c === '1:1') { sa = 'ERmandOne'; ea = 'ERmandOne';  }
  else if (r.c === 'M:N') { sa = 'ERmany';    ea = 'ERmany';     }
  else                     { sa = 'ERmandOne'; ea = 'ERmandMany'; }

  const dashed  = r.dashed ? 'dashed=1;' : '';
  const label   = r.dashed ? '(via mac_address)' : '';
  const lcolor  = r.dashed ? 'fontColor=#999999;' : '';

  return `    <mxCell id="e${i}" value="${esc(label)}" edge="1" `
       + `source="t_${r.s}" target="t_${r.t}" parent="1" `
       + `style="edgeStyle=entityRelationEdgeStyle;${dashed}`
       + `endArrow=${ea};startArrow=${sa};endFill=0;startFill=0;`
       + `strokeColor=#444444;strokeWidth=1.5;fontSize=10;${lcolor}" vertex="1">`
       + `<mxGeometry relative="1" as="geometry"/></mxCell>\n`;
}

function genBg(id, x, y, w, h, fill, stroke, label, lcolor) {
  let s = '';
  s += `    <mxCell id="bg_${id}" value="" `
     + `style="rounded=1;fillColor=${fill};strokeColor=${stroke};strokeWidth=2;opacity=60;" `
     + `vertex="1" parent="1">`
     + `<mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/>`
     + `</mxCell>\n`;
  s += `    <mxCell id="bgl_${id}" value="&lt;b&gt;${esc(label)}&lt;/b&gt;" `
     + `style="text;html=1;align=left;fontSize=11;fontColor=${lcolor};`
     + `fillColor=none;strokeColor=none;" `
     + `vertex="1" parent="1">`
     + `<mxGeometry x="${x+8}" y="${y+4}" width="240" height="20" as="geometry"/>`
     + `</mxCell>\n`;
  return s;
}

// ── Assemble final XML ────────────────────────────────────────
let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
xml += '<mxGraphModel dx="5000" dy="5000" grid="1" gridSize="10" guides="1" '
     + 'tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" '
     + 'pageWidth="3500" pageHeight="1600" math="0" shadow="1">\n';
xml += '  <root>\n';
xml += '    <mxCell id="0"/>\n';
xml += '    <mxCell id="1" parent="0"/>\n\n';

// Title
xml += `    <mxCell id="main_title" `
     + `value="&lt;b&gt;&lt;font style='font-size:19px'&gt;`
     + `SabaPost – Entity Relationship Diagram (ERD)&lt;/font&gt;&lt;/b&gt;`
     + `&lt;br/&gt;&lt;font color='#555555' style='font-size:12px'&gt;`
     + `نظام إدارة اللافتات الرقمية · Digital Signage Management System`
     + `&lt;/font&gt;" `
     + `style="text;html=1;align=center;strokeColor=none;fillColor=none;" `
     + `vertex="1" parent="1">`
     + `<mxGeometry x="900" y="14" width="900" height="55" as="geometry"/>`
     + `</mxCell>\n\n`;

// ── Zone backgrounds (drawn FIRST so they appear behind tables) ──
xml += genBg('auth',     30,  35, 570, 495, Z.auth.bg,     Z.auth.stroke,     'AUTH',             Z.auth.stroke);
xml += genBg('location', 608, 35, 240, 440, Z.location.bg, Z.location.stroke, 'LOCATION',         Z.location.stroke);
xml += genBg('loc',      608, 35, 240, 440, Z.location.bg, Z.location.stroke, '', Z.location.stroke);
xml += genBg('screens',  872, 35, 610, 800, Z.screens.bg,  Z.screens.stroke,  'SCREENS',          Z.screens.stroke);
xml += genBg('ads',     1508, 35, 590, 880, Z.ads.bg,      Z.ads.stroke,      'ADVERTISEMENTS',   Z.ads.stroke);
xml += genBg('finance', 2138, 35, 620, 1120, Z.finance.bg,  Z.finance.stroke,  'FINANCE',          Z.finance.stroke);
xml += genBg('support', 2788, 35, 295, 1120, Z.support.bg,  Z.support.stroke,  'SUPPORT & CONFIG', Z.support.stroke);
xml += '\n';

// ── Tables ────────────────────────────────────────────────────
for (const t of TABLES) {
  xml += genTable(t);
  xml += '\n';
}

// ── Relationships ─────────────────────────────────────────────
for (let i = 0; i < RELS.length; i++) {
  xml += genRel(RELS[i], i);
}

// ── Legend ────────────────────────────────────────────────────
xml += `\n    <mxCell id="leg_box" value="" `
     + `style="rounded=1;fillColor=#FAFAFA;strokeColor=#CCCCCC;shadow=1;" `
     + `vertex="1" parent="1">`
     + `<mxGeometry x="35" y="570" width="300" height="185" as="geometry"/>`
     + `</mxCell>\n`;
xml += `    <mxCell id="leg_hd" value="&lt;b&gt;Legend – مفتاح الرموز&lt;/b&gt;" `
     + `style="text;html=1;align=center;fontSize=13;" vertex="1" parent="1">`
     + `<mxGeometry x="35" y="575" width="300" height="25" as="geometry"/>`
     + `</mxCell>\n`;

const legendItems = [
  { v:`&lt;b&gt;&lt;font color='#7A6000'&gt;PK&lt;/font&gt;&lt;/b&gt;  Primary Key – مفتاح أساسي`, y:604 },
  { v:`&lt;i&gt;&lt;font color='#4A148C'&gt;FK&lt;/font&gt;&lt;/i&gt;  Foreign Key – مفتاح أجنبي`, y:626 },
  { v:`────|&lt;  1:N  واحد إلى أكثر (One to Many)`, y:648 },
  { v:`───||  1:1  واحد إلى واحد (One to One)`, y:670 },
  { v:`&lt;font color='#999999'&gt;- - - علاقة منطقية بدون FK رسمي&lt;/font&gt;`, y:692 },
];
let legRowsHtml = '';
for (const li of legendItems) {
  xml += `    <mxCell id="leg_${li.y}" value="${li.v}" `
       + `style="text;html=1;fontSize=11;" vertex="1" parent="1">`
       + `<mxGeometry x="48" y="${li.y}" width="280" height="20" as="geometry"/>`
       + `</mxCell>\n`;
}
// PK row example
xml += `    <mxCell id="leg_pkex" value="" `
     + `style="fillColor=#FFF9C4;strokeColor=#CCCCCC;" vertex="1" parent="1">`
     + `<mxGeometry x="48" y="716" width="90" height="17" as="geometry"/>`
     + `</mxCell>\n`;
xml += `    <mxCell id="leg_pktx" value="= PK row highlight" `
     + `style="text;html=1;fontSize=10;" vertex="1" parent="1">`
     + `<mxGeometry x="145" y="716" width="185" height="17" as="geometry"/>`
     + `</mxCell>\n`;

xml += '  </root>\n</mxGraphModel>\n';

// ── Write file ────────────────────────────────────────────────
const outPath = 'd:/projects/laravel/sabapost/database_erd_drawio.drawio';
fs.writeFileSync(outPath, xml, 'utf8');

const totalCols = TABLES.reduce((s, t) => s + t.cols.length, 0);
console.log('✅  Written :', outPath);
console.log(`📦  Tables  : ${TABLES.length}`);
console.log(`🔢  Columns : ${totalCols}`);
console.log(`🔗  Rels    : ${RELS.length}`);
console.log(`📄  Size    : ${(xml.length / 1024).toFixed(1)} KB`);
