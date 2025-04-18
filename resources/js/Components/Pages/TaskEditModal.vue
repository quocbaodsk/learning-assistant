<template>
    <a-modal v-model:open="visible" title="✏️ Chỉnh sửa nhiệm vụ" @ok="submit" ok-text="Lưu" cancel-text="Huỷ">
        <a-form layout="vertical">
            <a-form-item label="Nội dung">
                <a-input v-model:value="task.task" />
            </a-form-item>
            <a-form-item label="Thời lượng">
                <a-input v-model:value="task.duration" placeholder="30 phút" />
            </a-form-item>
            <a-form-item label="Nguồn tài liệu">
                <a-input v-model:value="task.resource" />
            </a-form-item>
            <a-form-item label="Loại">
                <a-select v-model:value="task.type" :options="types" />
            </a-form-item>
            <a-form-item label="Focus">
                <a-input v-model:value="task.focus" />
            </a-form-item>
        </a-form>
    </a-modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
    data: Object,
});
const emit = defineEmits(['update:modelValue', 'updated']);

const visible = ref(false);
const task = ref({});
const types = [
    'Video',
    'Article',
    'Exercise',
    'Book',
    'Podcast',
    'None',
].map(t => ({ label: t, value: t }));

watch(() => props.modelValue, val => (visible.value = val));
watch(() => visible.value, val => emit('update:modelValue', val));
watch(() => props.data, (val) => task.value = { ...val });

const submit = async () => {
    try {
        await axios.patch(`/api/learning/task/update/${task.value.id}`, task.value);
        emit('updated');
        visible.value = false;
    } catch (err) {
        console.error('Lỗi cập nhật task:', err);
    }
};
</script>