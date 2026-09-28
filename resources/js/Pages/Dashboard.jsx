import { Head, router, usePage } from '@inertiajs/react';

export default function Dashboard({ area }) {
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

                    <p className="text-sm text-zinc-600">Fitur lanjutan belum diimplementasikan pada phase ini.</p>
                </section>
            </main>
        </>
    );
}
