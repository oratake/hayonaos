import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import BoxForm from '@/Components/BoxForm';

export default function Edit({ auth, box }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        name: box.name || '',
        description: box.description || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('boxes.update', box.id), {
            // onSuccess: () => reset(), // Optionally reset or redirect
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">BOX編集</h2>
                    <Link href={route('boxes.index')} className="btn btn-sm btn-outline">
                        一覧へ戻る
                    </Link>
                </div>
            }
        >
            <Head title="BOX編集" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-base-100 shadow-sm sm:rounded-lg">
                        <BoxForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitButtonText="更新する"
                            isFocused={false} // 編集時は最初のフィールドに自動フォーカスしない場合
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}