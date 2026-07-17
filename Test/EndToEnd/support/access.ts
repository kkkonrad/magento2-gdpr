import { readFileSync } from 'node:fs';

export interface TestAccess {
  customerUrl: string;
  customerEmail: string;
  customerPassword: string;
  adminUrl: string;
  adminEmail: string;
  adminPassword: string;
}

export function loadTestAccess(): TestAccess {
  const file = process.env.GDPR_ACCESS_FILE || '/var/www/html/useraccess.md';
  const values = readFileSync(file, 'utf8')
    .split(/\r?\n/)
    .filter(line => line.includes(':'))
    .map(line => line.slice(line.indexOf(':') + 1).trim());

  if (values.length < 6 || values.some(value => value === '')) {
    throw new Error('The GDPR browser-test access file is incomplete.');
  }

  return {
    customerUrl: values[0],
    customerEmail: values[1],
    customerPassword: values[2],
    adminUrl: values[3],
    adminEmail: values[4],
    adminPassword: values[5]
  };
}
