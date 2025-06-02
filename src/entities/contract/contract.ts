import { SafeParseReturnType, z } from 'zod'
import { TContract } from './contract.types'
import getValidISOstring from '../../services/getValidISOstring.js'
import ReadonlyBaseClass from '../ReadonlyBaseClass.js'
import _ from 'lodash'

export class Contract extends ReadonlyBaseClass implements TContract {

	public id: number
	public uuid: string
	public version: string
	public synchronizationId: string
	public originId: string
	public originHash: string
	public sourceLastChanged: string
	public sourceLastChecked: string
	public sourceLastSynced: string
	public targetId: string
	public targetHash: string
	public targetLastChanged: string
	public targetLastChecked: string
	public targetLastSynced: string
	public targetLastAction: string
	public created: string
	public updated: string

	constructor(contract: TContract) {
		const processedContract: TContract = {
			id: contract.id || null,
			uuid: contract.uuid || '',
			version: contract.version || '0.0.1',
			synchronizationId: contract.synchronizationId || (contract as any).synchronization_id || '',
			originId: contract.originId || (contract as any).origin_id || '',
			originHash: contract.originHash || (contract as any).origin_hash || '',
			sourceLastChanged: contract.sourceLastChanged || (contract as any).source_last_changed || '',
			sourceLastChecked: contract.sourceLastChecked || (contract as any).source_last_checked || '',
			sourceLastSynced: contract.sourceLastSynced || (contract as any).source_last_synced || '',
			targetId: contract.targetId || (contract as any).target_id || '',
			targetHash: contract.targetHash || (contract as any).target_hash || '',
			targetLastChanged: contract.targetLastChanged || (contract as any).target_last_changed || '',
			targetLastChecked: contract.targetLastChecked || (contract as any).target_last_checked || '',
			targetLastSynced: contract.targetLastSynced || (contract as any).target_last_synced || '',
			targetLastAction: contract.targetLastAction || (contract as any).target_last_action || '',
			created: getValidISOstring(contract.created) ?? '',
			updated: getValidISOstring(contract.updated) ?? '',
		}

		super(processedContract)
	}

	public cloneRaw(): TContract {
		return _.cloneDeep(this)
	}

	public validate(): SafeParseReturnType<TContract, unknown> {
		const schema = z.object({
			id: z.number().nullable(),
			uuid: z.string().optional(),
			version: z.string().optional(),
			synchronizationId: z.string().optional(),
			originId: z.string().optional(),
			originHash: z.string().optional(),
			sourceLastChanged: z.string().optional(),
			sourceLastChecked: z.string().optional(),
			sourceLastSynced: z.string().optional(),
			targetId: z.string().optional(),
			targetHash: z.string().optional(),
			targetLastChanged: z.string().optional(),
			targetLastChecked: z.string().optional(),
			targetLastSynced: z.string().optional(),
			targetLastAction: z.string().optional(),
			created: z.string().optional(),
			updated: z.string().optional(),
		})

		return schema.safeParse({ ...this })
	}

	/**
	 * Generate a human-readable contract name
	 *
	 * @return {string} Generated contract name
	 */
	public getDisplayName(): string {
		if (this.originId && this.targetId) {
			return `Contract: ${this.originId} → ${this.targetId}`
		} else if (this.originId) {
			return `Contract: ${this.originId} → (pending)`
		} else if (this.id) {
			return `Contract ${this.id}`
		}
		return 'New Contract'
	}

	/**
	 * Calculate contract status based on sync dates and data
	 *
	 * @return {string} Contract status (active, inactive, error)
	 */
	public getStatus(): string {
		// If we have recent sync activity, consider it active
		if (this.sourceLastSynced || this.targetLastSynced) {
			const sourceTimestamp = this.sourceLastSynced ? new Date(this.sourceLastSynced).getTime() : 0
			const targetTimestamp = this.targetLastSynced ? new Date(this.targetLastSynced).getTime() : 0
			const lastSync = new Date(Math.max(sourceTimestamp, targetTimestamp))
			const daysSinceSync = (Date.now() - lastSync.getTime()) / (1000 * 60 * 60 * 24)
			
			if (daysSinceSync < 7) {
				return 'active'
			}
		}
		
		// If we have both origin and target, it's at least inactive
		if (this.originId && this.targetId) {
			return 'inactive'
		}
		
		// If missing critical data, consider it an error
		return 'error'
	}
} 