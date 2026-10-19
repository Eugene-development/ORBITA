import { redirect } from '@sveltejs/kit';
import type { LayoutServerLoad } from './$types';
export const load: LayoutServerLoad = ({ locals }) => { if (!locals.employee || !['admin', 'editor'].includes(locals.employee.role)) redirect(303, '/admin/login'); return { employee: locals.employee }; };
