import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function VerifyIndex() {
    const [token, setToken] = useState('');

    function submit(event) {
        event.preventDefault();
        router.visit(`/verify/${token}`);
    }

    return (
        <>
            <Head title="Verifikasi Dokumen" />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                        <p className="text-sm font-semibold text-emerald-800">Secure Document Signature</p>
                        <Link href="/login" className="text-sm font-semibold text-zinc-600 hover:text-zinc-950">Masuk</Link>
                    </div>
                </header>

                <div className="mx-auto grid max-w-5xl items-center gap-10 px-5 py-12 lg:min-h-[calc(100vh-65px)] lg:grid-cols-[minmax(0,1fr)_420px]">
                    <section>
                        <p className="text-sm font-semibold text-emerald-700">Verifikasi publik</p>
                        <h1 className="mt-2 max-w-xl text-3xl font-semibold text-zinc-950 sm:text-4xl">Verifikasi integritas dokumen</h1>
                        <p className="mt-4 max-w-xl text-base leading-7 text-zinc-600">
                            Gunakan token pada halaman pengesahan atau pindai QR code pada PDF final untuk melihat hasil verifikasi.
                        </p>
                        <dl className="mt-8 grid max-w-xl gap-4 sm:grid-cols-2">
                            <div className="border-l-2 border-emerald-600 pl-4">
                                <dt className="text-sm font-semibold text-zinc-900">Pemeriksaan dokumen</dt>
                                <dd className="mt-1 text-sm leading-6 text-zinc-600">Hash PDF dibandingkan dengan dokumen final yang tersimpan.</dd>
                            </div>
                            <div className="border-l-2 border-sky-600 pl-4">
                                <dt className="text-sm font-semibold text-zinc-900">Pemeriksaan signer</dt>
                                <dd className="mt-1 text-sm leading-6 text-zinc-600">Setiap tanda tangan digital diverifikasi secara independen.</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="ui-card p-6 sm:p-7" aria-labelledby="verify-form-title">
                        <h2 id="verify-form-title" className="text-lg font-semibold text-zinc-950">Masukkan token</h2>
                        <p className="mt-1 text-sm text-zinc-600">Token terdiri dari 43 karakter dan tercetak bersama QR code.</p>
                        <form className="mt-6 flex flex-col gap-5" onSubmit={submit}>
                            <div className="flex flex-col gap-2">
                                <label htmlFor="token" className="text-sm font-medium text-zinc-800">Token verifikasi</label>
                                <input id="token" type="text" value={token} onChange={(event) => setToken(event.target.value)} placeholder="Masukkan token verifikasi" autoFocus required className="ui-input font-mono" />
                            </div>
                            <button type="submit" disabled={token.trim() === ''} className="ui-button-primary w-full">Verifikasi dokumen</button>
                        </form>
                    </section>
                </div>
            </main>
        </>
    );
}
