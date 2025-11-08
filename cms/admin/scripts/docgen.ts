/* eslint-disable no-console */
import { promises as fs } from 'node:fs';
import * as path from 'node:path';

type CommentPart = { text?: string; kind?: string };

type TDComment = {
	summary?: CommentPart[];
};

type TDSource = {
	fileName: string;
};

type TDNode = {
	id: number;
	name: string;
	kindString?: string;
	kind?: number;
	comment?: TDComment;
	children?: TDNode[];
	signatures?: TDNode[];
	flags?: {
		isPrivate?: boolean;
		isProtected?: boolean;
		isInternal?: boolean;
		isExternal?: boolean;
		isExported?: boolean;
	};
	sources?: TDSource[];
	parameters?: TDNode[];
};

type ReactDocProp = {
	required: boolean;
	defaultValue?: { value: string };
	description?: string;
	type?: { name?: string };
};

type ReactDocEntry = {
	displayName: string;
	description?: string;
	filePath?: string;
	props?: Record<string, ReactDocProp>;
};

const root = process.cwd();
const tmpDir = path.join(root, 'tmp');
const docsRoot = path.join(root, 'docs');
const contentRoot = path.join(docsRoot, 'src', 'content', 'docs');
const refsDir = path.join(contentRoot, 'refs');
const modulesDir = path.join(contentRoot, '10-modules');
const componentsDir = path.join(contentRoot, '30-components');
const apisDir = path.join(contentRoot, '20-apis');
const overviewDir = path.join(contentRoot, '00-overview');
const sidebarPath = path.join(docsRoot, '_sidebar.yml');

const allowedKinds = new Set([
	'Class',
	'Enumeration',
	'Function',
	'Interface',
	'Namespace',
	'Variable',
]);
const allowedKindIds = new Set([32, 64, 128, 256, 4]);

const md = (value: string) => `${value.trim()}\n`;

const toSlug = (value: string) =>
	value
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');

const unixPath = (value: string) => value.replace(/\\/g, '/');

async function ensureDir(dir: string) {
	await fs.mkdir(dir, { recursive: true });
}

async function cleanDir(dir: string, keep: Set<string>) {
	const entries = await fs.readdir(dir);
	for (const entry of entries) {
		if (entry === '.gitkeep') {
			continue;
		}
		if (!keep.has(entry)) {
			await fs.rm(path.join(dir, entry), { recursive: true, force: true });
		}
	}
}

async function readJson<T>(p: string): Promise<T | null> {
	try {
		const raw = await fs.readFile(p, 'utf8');
		return JSON.parse(raw) as T;
	} catch {
		return null;
	}
}

const commentText = (comment?: TDComment): string => {
	if (!comment?.summary) {
		return '';
	}

	const parts = comment.summary
		.map((part) => {
			if (!part) {
				return '';
			}
			if (part.kind === 'code') {
				return `\`${part.text ?? ''}\``;
			}
			return part.text ?? '';
		})
		.join('')
		.trim();

	return parts;
};

const summaryOf = (node: TDNode): string => {
	const fromNode = commentText(node.comment);
	if (fromNode) {
		return fromNode;
	}
	const fromSignature = node.signatures?.map((sig) => commentText(sig.comment)).find((text) => text);
	return fromSignature ?? '';
};

const sourcesOf = (node: TDNode): string[] => {
	const files = new Set<string>();

	for (const source of node.sources ?? []) {
		files.add(unixPath(source.fileName));
	}

	for (const signature of node.signatures ?? []) {
		for (const source of signature.sources ?? []) {
			files.add(unixPath(source.fileName));
		}
	}

	return Array.from(files);
};

const flatten = (node: TDNode): TDNode[] => {
	const descendants = [
		...(node.children?.flatMap((child) => flatten(child)) ?? []),
		...(node.signatures ?? []),
	];
	return [node, ...descendants];
};

const isDocumented = (node: TDNode): boolean => {
	const kindName = node.kindString ?? '';
	if (!allowedKinds.has(kindName) && !(node.kind && allowedKindIds.has(node.kind))) {
		return false;
	}

	if (node.flags?.isPrivate || node.flags?.isProtected || node.flags?.isInternal) {
		return false;
	}

	const sources = sourcesOf(node);
	if (sources.length === 0) {
		return false;
	}

	if (!sources.some((file) => file.startsWith('src/'))) {
		return false;
	}

	if (node.flags?.isExported === false) {
		return false;
	}

	return true;
};

const frontMatter = (data: Record<string, unknown>) => {
	const lines = Object.entries(data)
		.map(([key, value]) => {
			if (Array.isArray(value)) {
				return `${key}: [${value.join(', ')}]`;
			}
			return `${key}: ${value}`;
		})
		.join('\n');

	return `---\n${lines}\n---\n`;
};

async function writeModuleCard(node: TDNode) {
	const slug = toSlug(node.name);
	const summary = summaryOf(node) || '—';
	const entrypoints = sourcesOf(node)
		.sort()
		.map((file) => `- ${file}:${node.name}`)
		.join('\n');

	const body = [
		frontMatter({
			id: slug,
			title: node.name,
			owner: 'team-admin',
			stability: 'experimental',
			tags: [],
		}),
		'',
		'## Summary',
		'',
		summary,
		'',
		'## Inputs',
		'',
		'- —',
		'',
		'## Outputs',
		'',
		'- —',
		'',
		'## Side effects',
		'',
		'- —',
		'',
		'## Dependencies',
		'',
		'- —',
		'',
		'## Entrypoints',
		'',
		entrypoints || '- —',
		'',
		'## Errors',
		'',
		'- —',
		'',
		'## Examples',
		'',
		'```ts',
		`// ${node.name} usage example`,
		'```',
		'',
	].join('\n');

	await fs.writeFile(path.join(modulesDir, `${slug}.md`), md(body), 'utf8');
	return slug;
}

async function writeReference(node: TDNode) {
    const slug = toSlug(node.name);
    const summary = summaryOf(node);

    const lines: string[] = [
        frontMatter({
            title: node.name,
        }),
        '',
        `**Kind:** ${node.kindString ?? '—'}`,
        '',
    ];

    if (summary) {
        lines.push(`> ${summary}`, '');
    }

    lines.push('<!-- TODO: Добавить сигнатуры и параметры -->', '');

    const body = lines.join('\n');

    await fs.writeFile(path.join(refsDir, `${slug}.md`), md(body), 'utf8');
    return slug;
}

const formatPropType = (prop?: ReactDocProp) => prop?.type?.name ?? 'unknown';

const formatDefault = (prop?: ReactDocProp) => prop?.defaultValue?.value ?? '—';

const formatPropDescription = (prop?: ReactDocProp) => (prop?.description?.trim() ? prop.description.trim() : '—');

async function writeComponentDocs(entry: ReactDocEntry) {
	const slug = toSlug(entry.displayName);
	const props = entry.props ?? {};
	const propNames = Object.keys(props).sort((a, b) => a.localeCompare(b));

	const tableRows =
		propNames.length > 0
			? ['| Prop | Type | Required | Default | Description |', '| --- | --- | --- | --- | --- |'].concat(
					propNames.map((name) => {
						const prop = props[name];
						return `| \`${name}\` | \`${formatPropType(prop)}\` | ${prop.required ? 'yes' : 'no'} | \`${formatDefault(
							prop,
						)}\` | ${formatPropDescription(prop)} |`;
					}),
				)
			: ['Нет публичных props.'];

	const body = [
		frontMatter({
			id: slug,
			title: entry.displayName,
			owner: 'team-admin',
			stability: 'experimental',
			tags: ['component'],
		}),
		'',
		'## Summary',
		'',
		entry.description?.trim() || '—',
		'',
		'## Props',
		'',
		...tableRows,
		'',
		'## Source',
		'',
		entry.filePath ? `- ${unixPath(entry.filePath)}` : '- —',
		'',
	].join('\n');

	await fs.writeFile(path.join(componentsDir, `${slug}.md`), md(body), 'utf8');
	return slug;
}

async function collectApis(): Promise<string[]> {
	try {
		const entries = await fs.readdir(apisDir);
		return entries
			.filter((name: string) => name.endsWith('.md') || name.endsWith('.mdx'))
			.map((name: string) => `20-apis/${name.replace(/\.mdx?$/, '')}`)
			.sort((a: string, b: string) => a.localeCompare(b));
	} catch {
		return [];
	}
}

async function collectOverview(): Promise<string[]> {
	const entries = await fs.readdir(overviewDir);
	return entries
		.filter((name: string) => name.endsWith('.md') || name.endsWith('.mdx'))
		.map((name: string) => `00-overview/${name.replace(/\.mdx?$/, '')}`)
		.sort((a: string, b: string) => a.localeCompare(b));
}

async function generate() {
	await Promise.all([ensureDir(refsDir), ensureDir(modulesDir), ensureDir(componentsDir)]);

	const typedocRoot =
		(await readJson<TDNode>(path.join(tmpDir, 'typedoc.json'))) ??
		(await readJson<TDNode>(path.join(tmpDir, 'jsdoc.json')));

	if (!typedocRoot) {
		console.error('Нет tmp/typedoc.json или tmp/jsdoc.json. Запусти npm run docs:gen:typedoc.');
		process.exit(2);
	}

	const rootNode: TDNode = typedocRoot;
	const allNodes = flatten(rootNode).filter(isDocumented);

	const moduleSlugs: string[] = [];
	const seenModuleSlugs = new Set<string>();
	const refSlugs: string[] = [];
	const seenRefSlugs = new Set<string>();

	for (const node of allNodes) {
		const moduleSlug = toSlug(node.name);
		if (!seenModuleSlugs.has(moduleSlug)) {
			seenModuleSlugs.add(moduleSlug);
			moduleSlugs.push(await writeModuleCard(node));
		}

		const refSlug = toSlug(node.name);
		if (!seenRefSlugs.has(refSlug)) {
			seenRefSlugs.add(refSlug);
			refSlugs.push(await writeReference(node));
		}
	}

	await cleanDir(
		modulesDir,
		new Set([...moduleSlugs.map((slug) => `${slug}.md`), '.gitkeep']),
	);
	await cleanDir(
		refsDir,
		new Set([...refSlugs.map((slug) => `${slug}.md`), '.gitkeep']),
	);

	const reactDocs = await readJson<ReactDocEntry[]>(path.join(tmpDir, 'react.json'));

	const componentSlugs: string[] = [];
	if (reactDocs?.length) {
		for (const entry of reactDocs) {
			componentSlugs.push(await writeComponentDocs(entry));
		}
	}

	await cleanDir(
		componentsDir,
		new Set([
			...componentSlugs.map((slug) => `${slug}.md`),
			'.gitkeep',
		]),
	);

	const overviewEntries = await collectOverview();
	const apiEntries = await collectApis();
	const moduleEntries = moduleSlugs.map((slug) => `10-modules/${slug}`).sort((a, b) => a.localeCompare(b));
	const componentEntries = componentSlugs.map((slug) => `30-components/${slug}`).sort((a, b) => a.localeCompare(b));
	const refEntries = refSlugs.map((slug) => `refs/${slug}`).sort((a, b) => a.localeCompare(b));

	const sidebarLines = [
		'# Generated by docgen. Do not edit manually.',
		...overviewEntries.map((entry) => `- ${entry}`),
		...moduleEntries.map((entry) => `- ${entry}`),
		...apiEntries.map((entry) => `- ${entry}`),
		...componentEntries.map((entry) => `- ${entry}`),
		...refEntries.map((entry) => `- ${entry}`),
	].join('\n');

	await fs.writeFile(sidebarPath, `${sidebarLines}\n`, 'utf8');

	const documented = allNodes.filter((node) => summaryOf(node)).length;
	const ratio = allNodes.length === 0 ? 1 : documented / allNodes.length;
	const percentage = Math.round(ratio * 100);

	console.log(`Doc coverage: ${documented}/${allNodes.length} (${percentage}%)`);

	if (process.argv.includes('--check') && ratio < 0.8) {
		console.error(`Docs coverage ${percentage}% < 80%`);
		process.exit(3);
	}
}

generate().catch((error) => {
	console.error(error);
	process.exit(1);
});

