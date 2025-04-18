<template>
    <div class="p-4 bg-white shadow sm:p-8 sm:rounded-lg">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div class="flex-grow">
                <h2 class="text-lg font-medium text-gray-900">Hồ sơ học tập</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Chọn hồ sơ học tập hoặc tạo mới
                </p>

                <div class="mt-2">
                    <select v-model="selectedProfileId" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @change="handleProfileChange" :disabled="loading">
                        <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                            {{ profile.primary_skill }} (Cấp độ: {{ profile.skill_level }}%)
                        </option>
                        <option value="new">+ Tạo hồ sơ mới</option>
                    </select>
                </div>
            </div>

            <div v-if="selectedProfile" class="flex-shrink-0">
                <button type="button"
                    class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-red-600 border border-transparent rounded-md hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                    @click="confirmDelete">
                    Xóa hồ sơ
                </button>
            </div>
        </div>

        <div v-if="selectedProfile" class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-3">
            <div class="p-3 rounded-lg bg-gray-50">
                <h3 class="font-medium text-gray-700">Kỹ năng chính</h3>
                <p class="text-lg">{{ selectedProfile.primary_skill }}</p>
                <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-blue-600 h-2.5 rounded-full" :style="{ width: `${selectedProfile.skill_level}%` }"></div>
                </div>
                <div class="mt-1 text-xs text-right text-gray-500">
                    {{ selectedProfile.skill_level }}%
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50">
                <h3 class="font-medium text-gray-700">Kỹ năng phụ</h3>
                <div class="flex flex-wrap gap-1 mt-1">
                    <span v-for="skill in selectedProfile.secondary_skills" :key="skill" class="px-2 py-1 text-xs text-gray-700 bg-gray-200 rounded-md">
                        {{ skill }}
                    </span>
                    <span v-if="!selectedProfile.secondary_skills?.length" class="text-sm text-gray-500">
                        Không có kỹ năng phụ
                    </span>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50">
                <h3 class="font-medium text-gray-700">Mục tiêu</h3>
                <p class="text-sm">{{ selectedProfile.goals || 'Chưa có mục tiêu cụ thể' }}</p>
            </div>
        </div>

        <!-- Modal xác nhận xóa -->
        <div v-if="confirmingDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500 bg-opacity-75">
            <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
                <h3 class="text-lg font-medium text-gray-900">Xác nhận xóa</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Bạn có chắc chắn muốn xóa hồ sơ học tập này không? Hành động này không thể hoàn tác.
                </p>

                <div class="flex justify-end mt-4 space-x-3">
                    <button type="button"
                        class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase transition duration-150 ease-in-out bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                        @click="confirmingDelete = false">
                        Hủy
                    </button>

                    <button type="button"
                        class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-red-600 border border-transparent rounded-md hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        :disabled="deleteLoading" @click="handleDelete">
                        <span v-if="deleteLoading" class="inline-block w-4 h-4 mr-2 border-2 border-white rounded-full border-t-transparent animate-spin"></span>
                        Xóa
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    selectedId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['selected']);

const profiles = ref([]);
const selectedProfileId = ref(null);
const loading = ref(false);
const confirmingDelete = ref(false);
const deleteLoading = ref(false);

// Computed property để lấy profile đang được chọn
const selectedProfile = computed(() => {
    return profiles.value.find(profile => profile.id === selectedProfileId.value) || null;
});

// Watch cho props.selectedId để cập nhật selectedProfileId
watch(() => props.selectedId, (newVal) => {
    selectedProfileId.value = newVal;
});

onMounted(async () => {
    await fetchProfiles();
    // Nếu có selectedId từ props, dùng nó
    if (props.selectedId) {
        selectedProfileId.value = props.selectedId;
    }
    // Nếu không, chọn profile đầu tiên nếu có
    else if (profiles.value.length > 0) {
        selectedProfileId.value = profiles.value[0].id;
        emit('selected', profiles.value[0]);
    }
});

const fetchProfiles = async () => {
    loading.value = true;
    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.get('/api/learning/profiles', {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        if (response.data.status === 200) {
            profiles.value = response.data.data || [];
        } else {
            console.error('Error in API response:', response.data);
        }
    } catch (error) {
        console.error('Error fetching profiles:', error);
    } finally {
        loading.value = false;
    }
};

const handleProfileChange = (event) => {
    const value = event.target.value;

    if (value === 'new') {
        // Reset selection và emit null để trigger form tạo mới
        selectedProfileId.value = null;
        emit('selected', null);
    } else {
        const selectedProfile = profiles.value.find(profile => profile.id === parseInt(value));
        emit('selected', selectedProfile);
    }
};

const confirmDelete = () => {
    confirmingDelete.value = true;
};

const handleDelete = async () => {
    if (!selectedProfileId.value) return;

    deleteLoading.value = true;
    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.delete(`/api/learning/profiles/${selectedProfileId.value}`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        if (response.data.status === 200) {
            confirmingDelete.value = false;

            // Reset selected profile
            selectedProfileId.value = null;
            emit('selected', null);

            // Refresh profiles
            await fetchProfiles();

            // Nếu còn profiles khác, chọn cái đầu tiên
            if (profiles.value.length > 0) {
                selectedProfileId.value = profiles.value[0].id;
                emit('selected', profiles.value[0]);
            }
        } else {
            console.error('Error in API response:', response.data);
        }
    } catch (error) {
        console.error('Error deleting profile:', error);
    } finally {
        deleteLoading.value = false;
    }
};
</script>