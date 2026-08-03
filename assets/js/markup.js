function markup(data) {
    if (!data) return '';

    let parsed = data.split('\n');
    let result = '';
    let inList = false;
    let paraBuffer = [];
    let inCodeBlock = false;
    let codeBuffer = [];
    let codeLang = '';

    // Flush any buffered plain-text lines out as a single <p>,
    // joining them with <br> so soft line breaks are preserved.
    function flushParagraph() {
        if (paraBuffer.length) {
            result += `<p>${paraBuffer.map(inlineFormat).join('<br>')}</p>\n`;
            paraBuffer = [];
        }
    }

    parsed.forEach((line) => {
        // Fenced code blocks: ``` or ```lang ... ```
        const fenceMatch = line.match(/^```(\S*)\s*$/);
        if (fenceMatch) {
            if (!inCodeBlock) {
                flushParagraph();
                if (inList) { result += '</ul>\n'; inList = false; }
                inCodeBlock = true;
                codeLang = fenceMatch[1] || '';
                codeBuffer = [];
            } else {
                // Closing fence: escape only, no inline formatting inside code
                const langClass = codeLang ? ` class="language-${escapeHtml(codeLang)}"` : '';
                const codeHtml = codeBuffer.map(escapeHtml).join('\n');
                result += `<pre><code${langClass}>${codeHtml}</code></pre>\n`;
                inCodeBlock = false;
                codeBuffer = [];
                codeLang = '';
            }
            return;
        }
        if (inCodeBlock) {
            codeBuffer.push(line);
            return;
        }

        // Headings: #, ##, ### etc.
        const headingMatch = line.match(/^(#{1,6})\s+(.*)/);
        if (headingMatch) {
            flushParagraph();
            if (inList) { result += '</ul>\n'; inList = false; }
            const level = headingMatch[1].length;
            const text = inlineFormat(headingMatch[2]);
            result += `<h${level}>${text}</h${level}>\n`;
            return;
        }

        // List items: - item or * item
        const listMatch = line.match(/^[-*]\s+(.*)/);
        if (listMatch) {
            flushParagraph();
            if (!inList) { result += '<ul>\n'; inList = true; }
            result += `<li>${inlineFormat(listMatch[1])}</li>\n`;
            return;
        }

        // Close list if we hit a non-list line
        if (inList) { result += '</ul>\n'; inList = false; }

        // Blank lines end the current paragraph
        if (line.trim() === '') {
            flushParagraph();
            return;
        }

        // Regular line: buffer it, don't wrap yet
        paraBuffer.push(line);
    });

    flushParagraph();
    if (inList) result += '</ul>\n';

    // Unclosed fence: render what we buffered anyway, don't silently drop it
    if (inCodeBlock && codeBuffer.length) {
        const langClass = codeLang ? ` class="language-${escapeHtml(codeLang)}"` : '';
        const codeHtml = codeBuffer.map(escapeHtml).join('\n');
        result += `<pre><code${langClass}>${codeHtml}</code></pre>\n`;
    }

    return result;
}

function inlineFormat(text) {
    const escaped = escapeHtml(text);

    // Pull out inline code spans first (`code`) and stash them, so the
    // markers inside (*, _, [ etc.) don't get formatted as markdown.
    const codeStash = [];
    const withCodePlaceholders = escaped.replace(/`([^`]+?)`/g, (match, code) => {
        codeStash.push(`<code>${code}</code>`);
        return `\u0000${codeStash.length - 1}\u0000`;
    });

    const formatted = withCodePlaceholders
        // Bold: **text** or __text__
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/__(.+?)__/g, '<strong>$1</strong>')
        // Italic: *text* or _text_
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/_(.+?)_/g, '<em>$1</em>')
        // Links: [text](url)
        .replace(/\[(.+?)\]\((.+?)\)/g, (m, label, url) => {
            const safe = /^(https?:|mailto:|\/|#)/i.test(url.trim());
            return safe
                ? `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`
                : label;
        });

    // Restore the code spans
    return formatted.replace(/\u0000(\d+)\u0000/g, (m, i) => codeStash[Number(i)]);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

const colorArray = [
  "#edc8c8", // red
  "#eddbc8", // orange
  "#ededc8", // yellow
  "#dbedc8", // yellow-green
  "#c8edc8", // green
  "#c8eddb", // spring green
  "#c8eded", // cyan
  "#c8dbed", // sky blue
  "#c8c8ed", // blue
  "#dbc8ed", // violet
  "#edc8ed", // magenta
  "#edc8db"  // pink
];

const randomColors = colorArray[Math.floor(Math.random() * colorArray.length)];
document.body.style.backgroundColor = randomColors;
