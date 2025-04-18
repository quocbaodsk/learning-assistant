<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Learning Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto space-y-6 max-w-7xl sm:px-6 lg:px-8">
                <ProfileSelector :selected-id="selectedProfile?.id" @selected="onSelectProfile" />

                <div v-if="!selectedProfile" class="p-4 bg-white shadow sm:p-8 sm:rounded-lg">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Tạo hồ sơ học tập mới</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Thêm thông tin về mục tiêu học tập của bạn.
                            </p>
                        </header>

                        <form @submit.prevent="createProfile" class="mt-6 space-y-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel for="primary_skill" value="Chọn chủ đề học" />
                                    <select id="primary_skill" v-model="form.primary_skill" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required
                                        @change="updateOptionsBasedOnPrimarySkill">
                                        <option value="JavaScript">JavaScript</option>
                                        <option value="Next.js">Next.js</option>
                                        <option value="Next.js với App Router">Next.js với App Router</option>
                                        <option value="Next.js với Pages Router">Next.js với Pages Router</option>
                                    </select>
                                    <InputError :message="errors.primary_skill" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="skill_level" value="Trình độ hiện tại (0-100)" />
                                    <input id="skill_level" v-model="form.skill_level" type="range" min="0" max="100" class="block w-full mt-1" required />
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Mới bắt đầu</span>
                                        <span>{{ form.skill_level }}%</span>
                                        <span>Thành thạo</span>
                                    </div>
                                </div>

                                <div class="col-span-1 md:col-span-2">
                                    <InputLabel for="secondary_skills" value="Kỹ năng phụ (chọn các kỹ năng liên quan)" />
                                    <div class="grid grid-cols-2 gap-2 mt-2 md:grid-cols-3">
                                        <div v-for="skill in suggestedSkillsByPrimarySkill" :key="skill" class="flex items-center">
                                            <input type="checkbox" :id="skill" :value="skill" v-model="selectedSecondarySkills" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                                            <label :for="skill" class="ml-2 text-sm text-gray-700">{{ skill }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-span-1 md:col-span-2">
                                    <InputLabel for="goals" value="Mục tiêu học tập" />
                                    <select id="goals" v-model="form.goals" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option v-for="goal in suggestedGoalsByPrimarySkill" :key="goal" :value="goal">
                                            {{ goal }}
                                        </option>
                                        <option value="">Khác (không chọn)</option>
                                    </select>
                                    <TextInput v-if="!form.goals" v-model="customGoal" placeholder="Nhập mục tiêu khác của bạn..." class="block w-full mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="learning_style" value="Phong cách học" />
                                    <select id="learning_style" v-model="form.learning_style" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="Visual">Trực quan (Visual) - Học qua hình ảnh, video</option>
                                        <option value="Reading/Writing">Đọc/Viết (Reading/Writing) - Học qua tài liệu, bài viết</option>
                                        <option value="Kinesthetic">Thực hành (Kinesthetic) - Học bằng cách code</option>
                                        <option value="Combined">Kết hợp (Học đa dạng phương pháp)</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="daily_learning_time" value="Thời gian học mỗi ngày" />
                                    <select id="daily_learning_time" v-model="form.daily_learning_time" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="30 minutes">30 phút</option>
                                        <option value="1 hour">1 giờ</option>
                                        <option value="2 hours">2 giờ</option>
                                        <option value="3+ hours">3+ giờ</option>
                                    </select>
                                </div>

                                <div class="col-span-1 md:col-span-2">
                                    <InputLabel for="preferred_resources" value="Nguồn tài liệu ưa thích" />
                                    <div class="grid grid-cols-2 gap-2 mt-2 md:grid-cols-3">
                                        <div v-for="resource in suggestedResources" :key="resource" class="flex items-center">
                                            <input type="checkbox" :id="resource" :value="resource" v-model="selectedResources" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                                            <label :for="resource" class="ml-2 text-sm text-gray-700">{{ resource }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-span-1 md:col-span-2">
                                    <InputLabel for="custom_ai_prompt" value="Yêu cầu riêng cho AI" />
                                    <TextArea id="custom_ai_prompt" v-model="form.custom_ai_prompt" class="block w-full mt-1" placeholder="Yêu cầu cụ thể khi AI tạo kế hoạch học cho bạn..." rows="3" />
                                </div>
                            </div>

                            <div v-if="loading" class="flex items-center">
                                <div class="inline-block w-4 h-4 mr-2 border-2 border-indigo-500 rounded-full animate-spin border-t-transparent"></div>
                                <span>Đang xử lý...</span>
                            </div>

                            <div v-if="error" class="p-4 rounded-md bg-red-50">
                                <div class="flex">
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Có lỗi xảy ra</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <p>{{ error }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="loading">Tạo hồ sơ</PrimaryButton>

                                <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" class="transition ease-in-out">
                                    <p v-if="successMessage" class="text-sm text-green-600">{{ successMessage }}</p>
                                </Transition>
                            </div>
                        </form>
                    </section>
                </div>

                <div v-if="selectedProfile" class="space-y-6">
                    <GeneratePrompt :profile-id="selectedProfile.id" @generated="refreshPlan" />
                    <WeeklyPlan :profile-id="selectedProfile.id" :key="refreshKey" />
                    <PdfExport :profile-id="selectedProfile.id" />
                    <HistoryWeeks :profile-id="selectedProfile.id" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import ProfileSelector from '@/Components/Pages/ProfileSelector.vue';
import GeneratePrompt from '@/Components/Pages/GeneratePrompt.vue';
import WeeklyPlan from '@/Components/Pages/WeeklyPlan.vue';
import PdfExport from '@/Components/Pages/PdfExport.vue';
import HistoryWeeks from '@/Components/Pages/HistoryWeeks.vue';
import axios from 'axios';

const selectedProfile = ref(null);
const refreshKey = ref(0);
const loading = ref(false);
const error = ref('');
const successMessage = ref('');
const errors = ref({});
const customGoal = ref('');
const selectedSecondarySkills = ref([]);
const selectedResources = ref([]);

// Kỹ năng phụ mặc định cho Next.js
const nextJsSkills = [
    'React',
    'JavaScript',
    'TypeScript',
    'HTML/CSS',
    'Tailwind CSS',
    'CSS-in-JS',
    'REST API',
    'GraphQL',
    'Redux',
    'React Query',
    'SWR',
    'Prisma',
    'MongoDB',
    'PostgreSQL',
    'Firebase',
    'Supabase',
    'Vercel',
    'AWS',
    'Docker'
];

// Kỹ năng phụ mặc định cho JavaScript
const javascriptSkills = [
    'HTML/CSS',
    'DOM Manipulation',
    'ES6+',
    'Async/Await',
    'Promises',
    'API Integration',
    'JSON',
    'Web Storage',
    'Node.js',
    'npm/yarn',
    'Webpack/Vite',
    'Testing',
    'Design Patterns',
    'Performance Optimization',
    'Debugging',
    'Browser APIs',
    'TypeScript',
    'React',
    'Vue.js',
    'Angular'
];

// Mục tiêu học tập mặc định cho Next.js
const nextJsGoals = [
    'Xây dựng website cá nhân bằng Next.js',
    'Phát triển ứng dụng thương mại điện tử với Next.js',
    'Tạo blog sử dụng Next.js và CMS',
    'Phát triển ứng dụng dashboard sử dụng Next.js',
    'Học Next.js để phát triển nghề nghiệp'
];

// Mục tiêu học tập mặc định cho JavaScript
const javascriptGoals = [
    'Học nền tảng JavaScript từ cơ bản',
    'Phát triển ứng dụng web tương tác với JavaScript',
    'Xây dựng Single Page Application',
    'Làm chủ ES6+ và các tính năng hiện đại',
    'Học JavaScript để chuyển sang framework (React, Vue, Angular)',
    'Phát triển ứng dụng Node.js',
    'Nâng cao kỹ năng JavaScript cho công việc'
];

const suggestedResources = [
    'Video tutorials',
    'Official documentation',
    'Blog articles',
    'Interactive exercises',
    'GitHub repositories',
    'Code examples',
    'Online courses',
    'Community forums',
    'Discord channels',
    'Workshops'
];

const form = ref({
    primary_skill: 'Next.js',
    skill_level: 50,
    secondary_skills: [],
    goals: 'Xây dựng website cá nhân bằng Next.js',
    learning_style: 'Visual',
    daily_learning_time: '1 hour',
    preferred_resources: [],
    custom_ai_prompt: 'Hướng dẫn cụ thể, tập trung vào các tài nguyên miễn phí, ví dụ thực tế và code.'
});

// Computed properties để lấy kỹ năng và mục tiêu phù hợp dựa trên primary_skill
const suggestedSkillsByPrimarySkill = computed(() => {
    if (form.value.primary_skill.includes('Next.js')) {
        return nextJsSkills;
    } else if (form.value.primary_skill === 'JavaScript') {
        return javascriptSkills;
    }
    return nextJsSkills; // Mặc định
});

const suggestedGoalsByPrimarySkill = computed(() => {
    if (form.value.primary_skill.includes('Next.js')) {
        return nextJsGoals;
    } else if (form.value.primary_skill === 'JavaScript') {
        return javascriptGoals;
    }
    return nextJsGoals; // Mặc định
});

// Hàm cập nhật các tùy chọn dựa trên primary_skill
const updateOptionsBasedOnPrimarySkill = () => {
    // Xóa các lựa chọn hiện tại
    selectedSecondarySkills.value = [];

    // Thiết lập lại các kỹ năng phụ mặc định dựa trên kỹ năng chính
    if (form.value.primary_skill.includes('Next.js')) {
        selectedSecondarySkills.value = ['React', 'JavaScript', 'HTML/CSS'];
        form.value.goals = nextJsGoals[0];
        form.value.custom_ai_prompt = 'Hướng dẫn cụ thể với Next.js, tập trung vào các tài nguyên miễn phí, ví dụ thực tế và code.';
    } else if (form.value.primary_skill === 'JavaScript') {
        selectedSecondarySkills.value = ['HTML/CSS', 'DOM Manipulation', 'ES6+'];
        form.value.goals = javascriptGoals[0];
        form.value.custom_ai_prompt = 'Hướng dẫn học JavaScript từng bước, giải thích rõ ràng, nhiều ví dụ và bài tập thực hành.';
    }
};

// Watch để cập nhật secondary_skills từ checkboxes
watch(selectedSecondarySkills, (newVal) => {
    form.value.secondary_skills = [...newVal];
});

// Watch để cập nhật preferred_resources từ checkboxes
watch(selectedResources, (newVal) => {
    form.value.preferred_resources = [...newVal];
});

// Watch để cập nhật mục tiêu tùy chỉnh
watch(customGoal, (newVal) => {
    if (newVal && !form.value.goals) {
        form.value.goals = newVal;
    }
});

// Watch để cập nhật refreshKey khi selectedProfile thay đổi
watch(selectedProfile, () => {
    refreshKey.value++;
});

onMounted(() => {
    // Thiết lập các giá trị mặc định cho checkboxes
    selectedSecondarySkills.value = ['React', 'JavaScript', 'HTML/CSS'];
    selectedResources.value = ['Video tutorials', 'Official documentation', 'Code examples'];
});

const createProfile = async () => {
    loading.value = true;
    error.value = '';
    successMessage.value = '';
    errors.value = {};

    // Sử dụng giá trị từ customGoal nếu không chọn mục tiêu có sẵn
    if (!form.value.goals && customGoal.value) {
        form.value.goals = customGoal.value;
    }

    // Chuẩn bị dữ liệu
    const formData = {
        ...form.value,
        secondary_skills: selectedSecondarySkills.value,
        preferred_resources: selectedResources.value
    };

    try {
        // Sử dụng API endpoint trực tiếp - đã cấu hình axios với token
        const response = await axios.post('/api/learning/profiles', formData);

        if (response.data.status === 201) {
            selectedProfile.value = response.data.data;
            successMessage.value = response.data.message || 'Đã tạo hồ sơ thành công!';
            refreshKey.value++; // Tăng refreshKey để buộc các component con load lại

            // Reset form
            form.value = {
                primary_skill: 'Next.js',
                skill_level: 50,
                secondary_skills: [],
                goals: 'Xây dựng website cá nhân bằng Next.js',
                learning_style: 'Visual',
                daily_learning_time: '1 hour',
                preferred_resources: [],
                custom_ai_prompt: 'Hướng dẫn cụ thể với Next.js, tập trung vào các tài nguyên miễn phí, ví dụ thực tế và code.'
            };
            customGoal.value = '';
            selectedSecondarySkills.value = ['React', 'JavaScript', 'HTML/CSS'];
            selectedResources.value = ['Video tutorials', 'Official documentation', 'Code examples'];
        } else {
            error.value = response.data.message || 'Có lỗi xảy ra khi tạo hồ sơ học tập.';
        }
    } catch (err) {
        console.error('Error creating profile:', err);
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
            error.value = err.response.data.message || 'Có lỗi trong dữ liệu nhập vào.';
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Có lỗi xảy ra khi tạo hồ sơ học tập.';
        }
    } finally {
        loading.value = false;
    }
};

const onSelectProfile = (profile) => {
    selectedProfile.value = profile;
    refreshKey.value++; // Tăng refreshKey để buộc các component con load lại
};

const refreshPlan = () => {
    refreshKey.value++;
};
</script>