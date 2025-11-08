// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	integrations: [
		starlight({
			title: 'stupidCms Admin Docs',
			description: 'Автогенерация документации для admin-панели stupidCms.',
			social: [],
			sidebar: [
				{
					label: 'Overview',
					autogenerate: { directory: '00-overview' },
				},
				{
					label: 'Modules',
					autogenerate: { directory: '10-modules' },
				},
				{
					label: 'APIs',
					autogenerate: { directory: '20-apis' },
				},
				{
					label: 'Components',
					autogenerate: { directory: '30-components' },
				},
				{
					label: 'Refs',
					autogenerate: { directory: 'refs' },
				},
			],
			tableOfContents: {
				minHeadingLevel: 2,
				maxHeadingLevel: 3,
			},
		}),
	],
});
