import { FieldError, MetaItem, SectionHeading, StatusBadge } from '../../Components/Ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const RESULT_CONFIG = {
    VALID: ['Dokumen Valid', 'border-emerald-300 bg-emerald-50 text-emerald-900', 'OK'],
    INCOMPLETE: [null, 'border-amber-300 bg-amber-50 text-amber-950', '!'],
    INVALID_DOCUMENT_MODIFIED: ['Dokumen Telah Diubah', 'border-red-300 bg-red-50 text-red-900', 'X'],
    INVALID_SIGNATURE: ['Tanda Tangan Tidak Valid', 'border-red-300 bg-red-50 text-red-900', 'X'],
    INVALID_PUBLIC_KEY: ['Public Key Tidak Valid', 'border-red-300 bg-red-50 text-red-900', 'X'],
    REJECTED_TOKEN_NOT_FOUND: ['Token Tidak Ditemukan', 'border-red-300 bg-red-50 text-red-900', 'X'],
};

export default function VerifyShow({ token, result, document }) {
    const [showAdvanced, setShowAdvanced] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        pdf: null,
        document_signer_id: '',
        custom_public_key: '',
    });

    function submitUpload(event) {
        event.preventDefault();
        post(`/verify/${token}`, { forceFormData: true });
    }

    const [configuredLabel, resultClasses, resultSymbol] = RESULT_CONFIG[result.code] ?? RESULT_CONFIG.INVALID_SIGNATURE;
    const resultLabel = result.code === 'INCOMPLETE'
        ? `Dokumen Belum Lengkap (${result.signed_count}/${result.total_signers})`
        : configuredLabel;
    const signedSigners = document?.signers?.filter((signer) => signer.status === 'SIGNED') ?? [];

    return (
        <>
            <Head title="Hasil Verifikasi" />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-4xl flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold text-emerald-800">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold text-zinc-950">Hasil Verifikasi</h1>
                        </div>
                        <Link href="/verify" className="ui-link">Verifikasi token lain</Link>
                    </div>
                </header>

                <div className="mx-auto flex max-w-4xl flex-col gap-6 px-5 py-8">
                    <section className={`rounded-md border p-5 ${resultClasses}`} aria-live="polite">
                        <div className="flex items-start gap-4">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-current text-sm font-bold" aria-hidden="true">{resultSymbol}</span>
                            <div className="min-w-0">
                                <h2 className="text-lg font-semibold">{resultLabel}</h2>
                                <p className="mt-1 font-mono text-xs break-all opacity-75">Token: {token}</p>
                            </div>
                        </div>
                    </section>

                    {document !== null && (
                        <>
                            <section className="ui-card">
                                <div className="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 className="text-xl font-semibold text-zinc-950">{document.title}</h2>
                                        <p className="mt-1 text-sm text-zinc-600">{document.institution}</p>
                                    </div>
                                    <StatusBadge status={document.status} />
                                </div>
                                <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                    <MetaItem label="Tanggal" value={document.document_date} />
                                    <MetaItem label="Status verifikasi" value={resultLabel} />
                                    <div className="sm:col-span-2">
                                        <MetaItem label="Document hash" value={document.document_hash} mono />
                                    </div>
                                </dl>
                            </section>

                            <section>
                                <SectionHeading title="Daftar penandatangan" description="Status dan waktu penandatanganan setiap signer." />
                                <div className="ui-card mt-4 overflow-hidden p-0">
                                    <div className="overflow-x-auto">
                                        <table className="ui-table">
                                            <thead>
                                                <tr>
                                                    <th>Urutan</th>
                                                    <th>Nama</th>
                                                    <th>Jabatan</th>
                                                    <th>Status</th>
                                                    <th>Waktu tanda tangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {document.signers.map((signer) => (
                                                    <tr key={signer.id}>
                                                        <td className="font-semibold">{signer.sign_order}</td>
                                                        <td className="font-medium text-zinc-950">{signer.name}</td>
                                                        <td className="text-zinc-600">{signer.position_title}</td>
                                                        <td><StatusBadge status={signer.status} /></td>
                                                        <td className="whitespace-nowrap text-zinc-500">
                                                            {signer.signed_at ? new Date(signer.signed_at).toLocaleString('id-ID') : '-'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <section className="ui-card">
                                <SectionHeading title="Verifikasi PDF" description="Upload PDF untuk membandingkan raw bytes dan memverifikasi tanda tangan digital." />
                                <form className="mt-6 flex flex-col gap-5" onSubmit={submitUpload}>
                                    <div className="flex flex-col gap-2">
                                        <label htmlFor="verification-pdf" className="text-sm font-medium text-zinc-800">File PDF</label>
                                        <input id="verification-pdf" type="file" accept="application/pdf" onChange={(event) => setData('pdf', event.target.files[0] ?? null)} required className="block w-full rounded-md border border-zinc-300 bg-white text-sm text-zinc-600 file:mr-3 file:border-0 file:bg-zinc-900 file:px-3 file:py-2.5 file:font-semibold file:text-white" />
                                        <FieldError>{errors.pdf}</FieldError>
                                    </div>

                                    <div className="rounded-md border border-zinc-200">
                                        <button type="button" onClick={() => setShowAdvanced((value) => !value)} aria-expanded={showAdvanced} aria-controls="advanced-verification" className="flex min-h-11 w-full items-center justify-between gap-4 rounded-md px-4 py-3 text-left text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                                            <span>
                                                Advanced Verification
                                                <span className="mt-0.5 block text-xs font-normal text-zinc-500">Uji satu signature menggunakan custom public key.</span>
                                            </span>
                                            <span aria-hidden="true">{showAdvanced ? '-' : '+'}</span>
                                        </button>

                                        {showAdvanced && (
                                            <div id="advanced-verification" className="flex flex-col gap-5 border-t border-zinc-200 p-4">
                                                <div className="flex flex-col gap-2">
                                                    <label htmlFor="target-signer" className="text-sm font-medium text-zinc-800">Target signer</label>
                                                    <select id="target-signer" value={data.document_signer_id} onChange={(event) => setData('document_signer_id', event.target.value)} className="ui-input">
                                                        <option value="">Pilih signer</option>
                                                        {signedSigners.map((signer) => (
                                                            <option key={signer.id} value={signer.id}>{signer.sign_order}. {signer.name} - {signer.position_title}</option>
                                                        ))}
                                                    </select>
                                                    <FieldError>{errors.document_signer_id}</FieldError>
                                                </div>

                                                <div className="flex flex-col gap-2">
                                                    <label htmlFor="custom-public-key" className="text-sm font-medium text-zinc-800">Custom public key (PEM)</label>
                                                    <textarea id="custom-public-key" value={data.custom_public_key} onChange={(event) => setData('custom_public_key', event.target.value)} rows={7} placeholder={'-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----'} className="ui-input min-h-36 resize-y font-mono text-xs" />
                                                    <FieldError>{errors.custom_public_key}</FieldError>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <button type="submit" disabled={processing} className="ui-button-primary self-start">
                                        {processing ? 'Memverifikasi...' : 'Verifikasi dengan upload'}
                                    </button>
                                </form>
                            </section>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
