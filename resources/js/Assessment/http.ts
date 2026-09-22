export class AssessmentHttpError extends Error {
    constructor(public status: number, public details: Record<string, string[]> = {}) {
        super(Object.values(details).flat().join(' ') || `Permintaan gagal (${status}).`);
    }
}

export async function assessmentRequest<T>(path: string, method: string, body?: unknown): Promise<T> {
    const value = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.split('=')[1];
    const response = await fetch(path, {
        method, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': value ? decodeURIComponent(value) : '' },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => null) as (T & { errors?: Record<string, string[]> }) | null;
    if (!response.ok) throw new AssessmentHttpError(response.status, data?.errors ?? {});
    return data as T;
}
