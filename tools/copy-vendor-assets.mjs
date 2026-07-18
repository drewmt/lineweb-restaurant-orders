import { copyFile, mkdir } from 'node:fs/promises';

const files = [
	['node_modules/tailwindcss/LICENSE', 'assets/vendor/tailwindcss/LICENSE'],
	['node_modules/@tailwindcss/cli/LICENSE', 'assets/vendor/tailwindcss-cli/LICENSE'],
	['node_modules/lucide/dist/umd/lucide.min.js', 'assets/vendor/lucide/lucide.min.js'],
	['node_modules/lucide/LICENSE', 'assets/vendor/lucide/LICENSE'],
	['node_modules/qrcodejs/qrcode.min.js', 'assets/vendor/qrcodejs/qrcode.min.js'],
	['node_modules/qrcodejs/LICENSE', 'assets/vendor/qrcodejs/LICENSE'],
];

for (const [source, destination] of files) {
	await mkdir(destination.substring(0, destination.lastIndexOf('/')), {recursive: true});
	await copyFile(source, destination);
}
