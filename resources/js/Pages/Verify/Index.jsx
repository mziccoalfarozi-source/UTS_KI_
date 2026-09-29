import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function VerifyIndex() {
    const [token, setToken] = useState('');

    function submit(e) {
        e.preventDefault();
        router.visit(`/verify/${token}`);
    }

    return (
        <>
            <Head title="Verifikasi Dokumen" />
            <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4 py-10">
                <section className="w-full max-w-md border border-zinc-200 bg-white p-7 shadow-sm">
                    <div className="flex flex-col gap-2">
                        <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                        <h1 className="text-2xl font-semibold">Verifikasi Dokumen</h1>
                        <p className="text-sm text-zinc-600">
                            Masukkan token verifikasi untuk memeriksa keaslian dokumen.
                        </p>
                    </div>

                    <form className="mt-7 flex flex-col gap-5" onSubmit={submit}>
                        <label className="flex flex-col gap-2 text-sm font-medium" htmlFor="token">
                            Token verifikasi
                            <input
                                id="token"
                                type="text"
                                value={token}
                                onChange={(e) => setToken(e.target.value)}
                                placeholder="Masukkan token verifikasi..."
                                autoFocus
                                required
                                className="h-11 border border-zinc-300 px-3 font-mono font-normal outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>

                        <button
                            type="submit"
                            disabled={token.trim() === ''}
                            className="h-11 bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Verifikasi
                        </button>
                    </form>
                </section>
            </main>
        </>
    );
}
