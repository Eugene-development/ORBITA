import type { Employee } from '@orbita/contracts';
declare global { namespace App { interface Locals { employee: Employee | null; } } }
export {};
