import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import TextareaInput from '@/Components/TextareaInput';

export default function BoxForm({ data, setData, errors, processing, onSubmit, submitButtonText = "保存する", isFocused = true }) {
    return (
        <form onSubmit={onSubmit} className="p-6 space-y-6">
            <div>
                <InputLabel htmlFor="name" value="BOX名" />
                <TextInput
                    id="name"
                    name="name"
                    value={data.name}
                    className="mt-1 block w-full"
                    autoComplete="name"
                    isFocused={isFocused}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="description" value="説明" />
                <TextareaInput
                    id="description"
                    name="description"
                    value={data.description}
                    className="mt-1 block w-full"
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} className="mt-2" />
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitButtonText}</PrimaryButton>
                <Link href={route('boxes.index')} className="btn btn-ghost">
                    キャンセル
                </Link>
            </div>
        </form>
    );
}