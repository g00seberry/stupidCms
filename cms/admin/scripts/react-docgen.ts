import { promises as fs } from 'node:fs';
import * as path from 'node:path';
import { withCustomConfig } from 'react-docgen-typescript';

const root = process.cwd();
const tmpDir = path.join(root, 'tmp');
const output = path.join(tmpDir, 'react.json');

async function main() {
	const parser = withCustomConfig(path.join(root, 'tsconfig.docgen.json'), {
		savePropValueAsString: true,
		propFilter: (prop) =>
			!(prop.parent && /\bnode_modules\b/.test(prop.parent.fileName)),
	});

	const docs = parser.parse(['src/**/*.tsx']);

	await fs.mkdir(tmpDir, { recursive: true });
	await fs.writeFile(output, JSON.stringify(docs, null, 2), 'utf8');
}

main().catch((error) => {
	console.error(error);
	process.exit(1);
});

