import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Dashboard({ area, signingKey = null }) {
    const { auth } = usePage().props;

    function logout() {
        router.post('/logout');
    }

    return (
        <>
            <Head title={`${area} Dashboard`} />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">{area} Dashboard</h1>
                        </div>
                        <button
                            type="button"
                            onClick={logout}
                            className="border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-zinc-50"
                        >
                            Logout
                        </button>
                    </div>
                </header>

                <section className="mx-auto flex max-w-5xl flex-col gap-6 px-5 py-10">
                    <div className="border border-zinc-200 bg-white p-6 shadow-sm">
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <div className="flex flex-col gap-1">
                                <dt className="text-xs font-semibold uppercase text-zinc-500">Nama</dt>
                                <dd className="font-medium">{auth.user.name}</dd>
                            </div>
                            <div className="flex flex-col gap-1">
                                <dt className="text-xs font-semibold uppercase text-zinc-500">Role</dt>
                                <dd className="font-medium">{auth.user.role}</dd>
                            </div>
                        </dl>
                    </div>

                    {area === 'SIGNER' && (
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="border border-zinc-200 bg-white p-6 shadow-sm">
                                <div className="flex h-full flex-col justify-between gap-4">
                                    <div className="flex flex-col gap-1">
                                        <h2 className="font-semibold">Dokumen Saya</h2>
                                        <p className="text-sm text-zinc-600">Lihat assignment dan urutan signing.</p>
                                    </div>
                                    <Link
                                        href="/documents"
                                        className="inline-flex h-10 items-center justify-center bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800"
                                    >
                                        Buka dokumen
                                    </Link>
                                </div>
                            </div>
                            <div className="border border-zinc-200 bg-white p-6 shadow-sm">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex flex-col gap-1">
                                        <h2 className="font-semibold">Signing Key</h2>
                                        <p className="text-sm text-zinc-600">
                                            {signingKey === null
                                                ? 'Signing key belum dibuat.'
                                                : `Signing key tersedia sejak ${new Date(signingKey.created_at).toLocaleString('id-ID')}.`}
                                        </p>
                                    </div>
                                    <Link
                                        href="/keys"
                                        className="inline-flex h-10 items-center justify-center bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800"
                                    >
                                        {signingKey === null ? 'Buat signing key' : 'Lihat signing key'}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}

                    {area === 'ADMIN' && (
                        <div className="border border-zinc-200 bg-white p-6 shadow-sm">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex flex-col gap-1">
                                    <h2 className="font-semibold">Document Management</h2>
                                    <p className="text-sm text-zinc-600">Kelola PDF final dan penugasan signer.</p>
                                </div>
                                <Link
                                    href="/documents"
                                    className="inline-flex h-10 items-center justify-center bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800"
                                >
                                    Buka dokumen
                                </Link>
                            </div>
                        </div>
                    )}

                </section>
            </main>
        </>
    );
}
