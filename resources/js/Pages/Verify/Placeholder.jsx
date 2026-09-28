import { Head } from '@inertiajs/react';

export default function VerificationPlaceholder({ token }) {
    return (
        <>
            <Head title="Verifikasi Dokumen" />
            <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-5 py-10">
                <section className="w-full max-w-lg border border-zinc-200 bg-white p-7 shadow-sm">
                    <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                    <h1 className="mt-2 text-xl font-semibold">Verifikasi Dokumen</h1>
                    <p className="mt-3 text-sm text-zinc-600">
                        Workflow verifikasi kriptografi belum tersedia pada phase ini.
                    </p>
                    <p className="mt-5 break-all font-mono text-xs text-zinc-500">{token}</p>
                </section>
            </main>
        </>
    );
}
