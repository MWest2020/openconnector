import { ref } from 'vue'
import { MissingParameterError } from '../../services/errors/index.js'
import { useLogStore } from './log'

// ... existing code ...

/**
 * Refresh the endpoint logs
 * @param filters - Optional filters to apply to the logs
 * @return {Promise<{ response: Response, data: object[] }>} The response and data
 */
const refreshEndpointLogs = async (filters: object = {}) => {
    const logStore = useLogStore()
    logStore.setLogsLoading(true)
    
    try {
        // Build query parameters
        const queryParams = new URLSearchParams()
        // Only add endpoint_id if not already present in filters
        if (!('endpoint_id' in filters) && endpointItem.value?.id) {
            queryParams.append('endpoint_id', endpointItem.value.id.toString())
        }
        // Add other filters
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                queryParams.append(key, value.toString())
            }
        })
        // Build the endpoint
        const endpoint = `/index.php/apps/openconnector/api/endpoints/logs${queryParams.toString() ? '?' + queryParams.toString() : ''}`
        const response = await fetch(endpoint, {
            method: 'GET',
        })
        const data = await response.json()
        setEndpointLogs(data)
        return { response, data }
    } catch (error) {
        console.error('Error refreshing endpoint logs:', error)
        throw error
    } finally {
        logStore.setLogsLoading(false)
    }
}

// ... existing code ... 