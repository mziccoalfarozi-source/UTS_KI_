import { Head, Link, useForm } from '@inertiajs/react';

export default function KeyIndex({ signingKey }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        signing_passphrase: '',
        signing_passphrase_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/keys', {
            onFinish: () => reset('signing_passphrase', 'signing_passphrase_confirmation'),
        });
    }

    return (
        <>
            <Head title="Signing Key" />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Signing Key</h1>
                        </div>
                        <Link href="/signer/dashboard" className="text-sm font-semibold text-zinc-700 hover:text-zinc-950">
                            Kembali
                        </Link>
                    </div>
                </header>

                <section className="mx-auto max-w-3xl px-5 py-10">
                    {signingKey !== null ? (
                        <div className="border border-zinc-200 bg-white p-6 shadow-sm">
                            <h2 className="font-semibold">Signing key tersedia</h2>
                            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                <div className="flex flex-col gap-1">
                                    <dt className="text-xs font-semibold uppercase text-zinc-500">Algorithm</dt>
                                    <dd className="font-medium">{signingKey.algorithm}</dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-xs font-semibold uppercase text-zinc-500">Dibuat</dt>
                                    <dd className="font-medium">
                                        {new Date(signingKey.created_at).toLocaleString('id-ID')}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-5 text-sm text-zinc-600">
                                Signing key tidak dapat diganti, dihapus, atau dirotasi.
                            </p>
                        </div>
                    ) : (
                        <form className="border border-zinc-200 bg-white p-6 shadow-sm" onSubmit={submit}>
                            <div className="flex flex-col gap-2">
                                <h2 className="font-semibold">Buat signing key</h2>
                                <p className="text-sm text-zinc-600">
                                    Gunakan passphrase khusus signing dengan minimal 12 karakter.
                                </p>
                            </div>

                            <div className="mt-6 flex flex-col gap-5">
                                <label className="flex flex-col gap-2 text-sm font-medium" htmlFor="signing-passphrase">
                                    Signing passphrase
                                    <input
                                        id="signing-passphrase"
                                        type="password"
                                        value={data.signing_passphrase}
                                        onChange={(event) => setData('signing_passphrase', event.target.value)}
                                        autoComplete="new-password"
                                        minLength={12}
                                        required
                                        className="h-11 border border-zinc-300 px-3 font-normal outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                    />
                                    {errors.signing_passphrase && (
                                        <span className="font-normal text-red-700">{errors.signing_passphrase}</span>
                                    )}
                                </label>

                                <label
                                    className="flex flex-col gap-2 text-sm font-medium"
                                    htmlFor="signing-passphrase-confirmation"
                                >
                                    Konfirmasi signing passphrase
                                    <input
                                        id="signing-passphrase-confirmation"
                                        type="password"
                                        value={data.signing_passphrase_confirmation}
                                        onChange={(event) =>
                                            setData('signing_passphrase_confirmation', event.target.value)
                                        }
                                        autoComplete="new-password"
                                        minLength={12}
                                        required
                                        className="h-11 border border-zinc-300 px-3 font-normal outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="h-11 bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing ? 'Membuat key...' : 'Buat signing key'}
                                </button>
                            </div>
                        </form>
                    )}
                </section>
            </main>
        </>
    );
}
