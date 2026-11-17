import { existsSync, readFileSync, writeFileSync, copyFileSync, mkdirSync } from 'node:fs';
import tls from 'node:tls';
import { randomBytes } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
const certificateDirectory = resolve(root, 'storage/app/private/certificates');
mkdirSync(certificateDirectory, { recursive: true });
const certificates = new Set([...tls.rootCertificates, ...(tls.getCACertificates ? tls.getCACertificates('system') : [])]);
writeFileSync(resolve(certificateDirectory, 'ca.pem'), [...certificates].join('\n'));
const envPath = resolve(root, '.env');
if (!existsSync(envPath)) copyFileSync(resolve(root, '.env.example'), envPath);
let env = readFileSync(envPath, 'utf8');
if (!/^APP_KEY\s*=\s*["']?[^\s"'\r\n]+/m.test(env)) {
    const key = 'APP_KEY=base64:' + randomBytes(32).toString('base64');
    env = /^APP_KEY\s*=/m.test(env) ? env.replace(/^APP_KEY\s*=.*$/m, key) : env + '\n' + key + '\n';
    writeFileSync(envPath, env);
}
if (!existsSync(resolve(root, 'vendor/autoload.php'))) {
    const result = spawnSync('composer', ['install', '--no-interaction', '--prefer-dist'], { cwd: root, stdio: 'inherit', shell: process.platform === 'win32' });
    if (result.error || result.status !== 0) {
        console.error('Не удалось установить зависимости сервера. Проверьте наличие PHP и Composer.');
        process.exit(1);
    }
}
if (/^DB_CONNECTION\s*=\s*["']?sqlite["']?\s*$/m.test(env) && !/^DB_DATABASE\s*=\s*[^\r\n]+/m.test(env)) {
    const database = resolve(root, 'database/database.sqlite');
    if (!existsSync(database)) writeFileSync(database, '');
}
console.log('Подготовка приложения завершена.');
