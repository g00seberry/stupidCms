import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const markdownLinkCheck = require('markdown-link-check');

const docsDir = path.join(process.cwd(), 'docs', 'src', 'content');

async function collectMarkdownFiles(dir) {
	const entries = await readdir(dir, { withFileTypes: true });
	const files = await Promise.all(
		entries.map(async (entry) => {
			const entryPath = path.join(dir, entry.name);
			if (entry.isDirectory()) {
				return collectMarkdownFiles(entryPath);
			}
			if (entry.isFile() && (entry.name.endsWith('.md') || entry.name.endsWith('.mdx'))) {
				return [entryPath];
			}
			return [];
		}),
	);

	return files.flat();
}

function checkFile(filePath) {
	return new Promise((resolve, reject) => {
		readFile(filePath, 'utf8')
			.then((markdown) => {
				markdownLinkCheck(
					markdown,
					{
						baseUrl: `file://${path.dirname(filePath)}/`,
						ignorePatterns: [{ pattern: '^mailto:' }],
						timeout: 5000,
					},
					(err, results) => {
						if (err) {
							reject(err);
							return;
						}

						const broken = results.filter((result) => result.status === 'dead');
						resolve({ filePath, broken });
					},
				);
			})
			.catch(reject);
	});
}

async function main() {
	const markdownFiles = await collectMarkdownFiles(docsDir);
	let brokenCount = 0;

	for (const file of markdownFiles) {
		// eslint-disable-next-line no-await-in-loop
		const { broken } = await checkFile(file);
		if (broken.length > 0) {
			brokenCount += broken.length;
			console.error(`Broken links in ${file}:`);
			for (const item of broken) {
				console.error(`  - ${item.link} (${item.statusCode ?? 'unknown'})`);
			}
		}
	}

	if (brokenCount > 0) {
		process.exit(4);
	}
}

main().catch((error) => {
	console.error(error);
	process.exit(1);
});

