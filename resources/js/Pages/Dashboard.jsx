import AppShell from '../Components/AppShell';
import { Card, MetaItem, SectionHeading, StatusBadge } from '../Components/Ui';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Dashboard({ area, signingKey = null }) {
    const { auth } = usePage().props;
    const isAdmin = area === 'ADMIN';

    return (
        <>
            <Head title={isAdmin ? 'Dashboard Admin' : 'Dashboard Penandatangan'} />
            <AppShell
                title={isAdmin ? 'Dashboard Admin' : 'Dashboard Penandatangan'}
                maxWidth="max-w-5xl"
                actions={
                    <button type="button" onClick={() => router.post('/logout')} className="ui-button-secondary">
                        Keluar
                    </button>
                }
            >
                <div className="flex flex-col gap-7">
                    <Card className="p-5 sm:p-6">
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <MetaItem label="Nama" value={auth.user.name} />
                            <MetaItem label="Role">
                                <StatusBadge status={auth.user.role} />
                            </MetaItem>
                        </dl>
                    </Card>

                    <section>
                        <SectionHeading
                            title="Akses utama"
                            description="Pilih area kerja sesuai kebutuhan Anda."
                        />
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            {isAdmin ? (
                                <>
                                    <ActionCard
                                        title="Manajemen Dokumen"
                                        description="Kelola PDF final dan penugasan penandatangan."
                                        href="/documents"
                                        action="Buka dokumen"
                                    />
                                    <ActionCard
                                        title="Log Verifikasi"
                                        description="Lihat riwayat dan hasil proses verifikasi dokumen."
                                        href="/admin/logs"
                                        action="Lihat log"
                                    />
                                </>
                            ) : (
                                <>
                                    <ActionCard
                                        title="Dokumen Saya"
                                        description="Lihat assignment, urutan, dan status penandatanganan."
                                        href="/documents"
                                        action="Buka dokumen"
                                    />
                                    <ActionCard
                                        title="Signing Key"
                                        description={
                                            signingKey === null
                                                ? 'Signing key belum dibuat.'
                                                : `Tersedia sejak ${new Date(signingKey.created_at).toLocaleString('id-ID')}.`
                                        }
                                        href="/keys"
                                        action={signingKey === null ? 'Buat signing key' : 'Lihat signing key'}
                                        status={signingKey === null ? 'Belum tersedia' : 'Siap digunakan'}
                                    />
                                </>
                            )}
                        </div>
                    </section>
                </div>
            </AppShell>
        </>
    );
}

function ActionCard({ title, description, href, action, status = null }) {
    return (
        <Card className="flex min-h-48 flex-col justify-between p-5 sm:p-6">
            <div>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="font-semibold text-zinc-950">{title}</h3>
                    {status && <span className="text-xs font-semibold text-zinc-500">{status}</span>}
                </div>
                <p className="mt-2 text-sm leading-6 text-zinc-600">{description}</p>
            </div>
            <Link href={href} className="ui-button-primary mt-6 w-full sm:w-fit">
                {action}
            </Link>
        </Card>
    );
}
