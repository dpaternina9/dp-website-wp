// VERBATIM from the live Claude Design project, PostBlocks.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.

const SAMPLE = [
  { p: 'Pass a `body` array of block objects. Each key names the block: p, h2, h3, h4, quote, ul, ol, code, note, image, table, rule.' },
  { h2: 'Headings run three deep' },
  { p: 'Level two splits a post into parts. It is the only heading most posts need.' },
  { ul: ['One idea per item, no trailing punctuation.', 'Sentence case, same as everything else.'] },
  { quote: 'A pull quote is for a line worth stopping on.', cite: 'The house style' },
  { rule: true },
];

class Component extends DCLogic {
  renderVals() {
    const body = this.props.body ?? SAMPLE;
    return {
      blocks: body.map((b, i) => ({
        key: i,
        isP: !!b.p,
        // `h` is an accepted alias for `h2`.
        isH2: !!b.h2 || !!b.h,
        isH3: !!b.h3,
        isH4: !!b.h4,
        isQuote: !!b.quote,
        hasCite: !!b.cite,
        cite: b.cite ?? '',
        isList: !!(b.ul || b.ol),
        // THE MARKER RULE: ordered lists are zero-padded two-digit numbers ("01"),
        // unordered lists are an em dash. Both are rendered, never native.
        items: (b.ul || b.ol || []).map((t, j) => ({
          key: j, text: t, marker: b.ol ? String(j + 1).padStart(2, '0') : '—',
        })),
        isCode: !!b.code,
        code: b.code ?? '',
        codeLabel: b.codeLabel ?? 'SHELL',
        isNote: !!b.note,
        note: b.note ?? '',
        noteLabel: b.noteLabel ?? 'NOTE',
        isImage: !!b.image,
        slotId: b.image ?? '',
        caption: b.caption ?? '',
        isRule: !!b.rule,
        isTable: !!b.table,
        tableHead: ((b.table || {}).head || []).map((t, j) => ({ key: j, text: t })),
        tableRows: ((b.table || {}).rows || []).map((row, j) => ({
          key: j, cells: row.map((t, k) => ({ key: k, text: t })),
        })),
        text: b.p || b.h || b.h2 || b.h3 || b.h4 || b.quote || b.note || '',
      })),
    };
  }
}
