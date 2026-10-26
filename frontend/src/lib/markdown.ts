/**
 * Tiny markdown-to-HTML for our own content files (legal pages): ## headings, paragraphs, "- " lists,
 * **bold** and [links](/path). Everything else is escaped. Not for user input.
 */
export function renderMarkdown(source: string): string {
  const escape = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
  const inline = (s: string) =>
    escape(s)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_, text: string, href: string) => `<a href="${href}">${text}</a>`)

  return source
    .trim()
    .split(/\n\s*\n/)
    .map((block) => {
      const lines = block.split('\n')
      const heading = /^(#{2,3}) (.*)$/.exec(lines[0])
      if (heading && lines.length === 1) return `<h${heading[1].length}>${inline(heading[2])}</h${heading[1].length}>`
      if (lines.every((l) => l.startsWith('- '))) return `<ul>${lines.map((l) => `<li>${inline(l.slice(2))}</li>`).join('')}</ul>`
      return `<p>${inline(lines.join(' '))}</p>`
    })
    .join('\n')
}
