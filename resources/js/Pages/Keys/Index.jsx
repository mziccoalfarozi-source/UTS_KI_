import AppShell, { BackLink } from '../../Components/AppShell';
import { Alert, Card, FieldError, MetaItem, StatusBadge } from '../../Components/Ui';
import { Head, useForm } from '@inertiajs/react';

export default function KeyIndex({ signingKey }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        signing_passphrase: '',
        signing_passphrase_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/keys', { onFinish: () => reset('signing_passphrase', 'signing_passphrase_confirmation') });
    }

    return (
        <>
            <Head title="Signing Key" />
            <AppShell title="Signing Key" maxWidth="max-w-3xl" actions={<BackLink href="/signer/dashboard">Dashboard</BackLink>}>
                {signingKey !== null ? (
                    <div className="flex flex-col gap-4">
                        <Alert tone="success">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <span className="font-semibold">Signing key siap digunakan.</span>
                                <StatusBadge status="COMPLETED" />
                            </div>
                        </Alert>
                        <Card className="p-5 sm:p-6">
                            <h2 className="font-semibold text-zinc-950">Metadata signing key</h2>
                            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                <MetaItem label="Algoritma" value={signingKey.algorithm} mono />
                                <MetaItem label="Dibuat" value={new Date(signingKey.created_at).toLocaleString('id-ID')} />
                            </dl>
                            <p className="mt-6 border-t border-zinc-200 pt-5 text-sm text-zinc-600">
                                Signing key tidak dapat diganti, dihapus, atau dirotasi.
                            </p>
                        </Card>
                    </div>
                ) : (
                    <Card className="p-5 sm:p-7">
                        <h2 className="text-lg font-semibold text-zinc-950">Buat signing key</h2>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-zinc-600">
                            Signing key digunakan untuk membuat tanda tangan digital. Gunakan passphrase khusus minimal 12 karakter; passphrase tidak disimpan oleh aplikasi.
                        </p>
                        <form className="mt-6 flex flex-col gap-5" onSubmit={submit}>
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
                                    className="ui-input"
                                />
                                <FieldError>{errors.signing_passphrase}</FieldError>
                            </label>
                            <label className="flex flex-col gap-2 text-sm font-medium" htmlFor="signing-passphrase-confirmation">
                                Konfirmasi signing passphrase
                                <input
                                    id="signing-passphrase-confirmation"
                                    type="password"
                                    value={data.signing_passphrase_confirmation}
                                    onChange={(event) => setData('signing_passphrase_confirmation', event.target.value)}
                                    autoComplete="new-password"
                                    minLength={12}
                                    required
                                    className="ui-input"
                                />
                            </label>
                            <button type="submit" disabled={processing} className="ui-button-primary sm:w-fit">
                                {processing ? 'Membuat key...' : 'Buat signing key'}
                            </button>
                        </form>
                    </Card>
                )}
            </AppShell>
        </>
    );
}
